<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Csp\Test\Unit\Model\Mode;

use Magento\Csp\Model\Mode\ReportingEndpointConfig;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test for \Magento\Csp\Model\Mode\ReportingEndpointConfig
 */
class ReportingEndpointConfigTest extends TestCase
{
    /**
     * The most specific setting wins: page endpoint, page report-uri, then the area endpoint
     *
     * @param string $area
     * @param array $configured config path => value
     * @param string|null $expected
     * @return void
     */
    #[DataProvider('endpointDataProvider')]
    public function testGetEndpointUrl(string $area, array $configured, ?string $expected): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(
            static function (string $path, string $scope) use ($configured) {
                return $scope === ScopeInterface::SCOPE_STORE ? ($configured[$path] ?? null) : null;
            }
        );
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_index_index');

        $model = new ReportingEndpointConfig($config, $this->createMock(Store::class), $state, $request);

        $this->assertSame($expected, $model->getEndpointUrl());
    }

    /**
     * @return array
     */
    public static function endpointDataProvider(): array
    {
        $page = 'csp/mode/storefront_checkout_index_index/report_to';
        $storefront = 'csp/mode/storefront/report_to';

        return [
            'page endpoint' => [
                Area::AREA_FRONTEND,
                [$page => 'https://reports.example.com/page', $storefront => 'https://reports.example.com/all'],
                'https://reports.example.com/page',
            ],
            'area default when the page has none' => [
                Area::AREA_FRONTEND,
                [$page => '', $storefront => 'https://reports.example.com/all'],
                'https://reports.example.com/all',
            ],
            'none configured' => [Area::AREA_FRONTEND, [], null],
            'admin area default' => [
                Area::AREA_ADMINHTML,
                [$storefront => 'https://reports.example.com/all', 'csp/mode/admin/report_to' => 'https://r.example/a'],
                'https://r.example/a',
            ],
            'other areas have no CSP mode config' => [Area::AREA_CRONTAB, [$storefront => 'https://r.example/'], null],
            'page report-uri beats the area endpoint (null: the caller uses the page report-uri)' => [
                Area::AREA_FRONTEND,
                [
                    'csp/mode/storefront_checkout_index_index/report_uri' => 'https://reports.example.com/page-uri',
                    $storefront => 'https://reports.example.com/all',
                ],
                null,
            ],
            'admin page endpoint' => [
                Area::AREA_ADMINHTML,
                [
                    'csp/mode/admin_checkout_index_index/report_to' => 'https://r.example/admin-page',
                    $page => 'https://r.example/storefront-page',
                    'csp/mode/admin/report_to' => 'https://r.example/a',
                ],
                'https://r.example/admin-page',
            ],
            'blank values are not endpoints' => [
                Area::AREA_FRONTEND,
                [$page => '  ', $storefront => ' https://reports.example.com/all '],
                'https://reports.example.com/all',
            ],
        ];
    }
}
