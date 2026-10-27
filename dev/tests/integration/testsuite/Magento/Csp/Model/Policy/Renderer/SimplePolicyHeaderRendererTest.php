<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Csp\Model\Policy\Renderer;

use Magento\Framework\App\Request\Http;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Response\Http as HttpResponse;

/**
 * Test that rendering policies via headers works.
 */
class SimplePolicyHeaderRendererTest extends TestCase
{
    /**
     * @var SimplePolicyHeaderRenderer
     */
    private $renderer;

    /**
     * @var HttpResponse
     */
    private $response;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->renderer = Bootstrap::getObjectManager()->get(SimplePolicyHeaderRenderer::class);
        $this->response = Bootstrap::getObjectManager()->create(HttpResponse::class);
    }

    /**
     * Test policy rendering in restrict mode.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 0
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri 0
     *
     * @return void
     */
    public function testRenderRestrictMode(): void
    {
        $policy = new FetchPolicy('default-src', false, ['https://magento.com'], [], true);

        $this->renderer->render($policy, $this->response);

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy'));
        $this->assertEmpty($this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEquals('default-src https://magento.com \'self\';', $header->getFieldValue());
    }

    /**
     * Test policy rendering in restrict mode with report URL provided.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 0
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri /csp-reports/
     *
     * @return void
     */
    public function testRenderRestrictWithReportingMode(): void
    {
        $policy = new FetchPolicy('default-src', false, ['https://magento.com'], [], true);

        $this->renderer->render($policy, $this->response);

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy'));
        $this->assertEmpty($this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEquals(
            'default-src https://magento.com \'self\'; report-uri /csp-reports/; report-to report-endpoint;',
            $header->getFieldValue()
        );
        $this->assertNotEmpty($endpointsHeader = $this->response->getHeader('Reporting-Endpoints'));
        $this->assertEquals('report-endpoint="/csp-reports/"', $endpointsHeader->getFieldValue());
        $this->assertEmpty($this->response->getHeader('Report-To'));
    }

    /**
     * Test policy rendering in report-only mode.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri 0
     *
     * @return void
     */
    public function testRenderReportMode(): void
    {
        $policy = new FetchPolicy(
            'default-src',
            false,
            ['https://magento.com'],
            ['https'],
            true,
            false,
            true,
            ['5749837589457695'],
            ['B2yPHKaXnvFWtRChIbabYmUBFZdVfKKXHbWtWidDVF8=' => 'sha256'],
            true,
            true
        );

        $this->renderer->render($policy, $this->response);

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEmpty($this->response->getHeader('Content-Security-Policy'));
        $this->assertEquals(
            'default-src https://magento.com https: \'self\' \'unsafe-eval\' \'strict-dynamic\''
            . ' \'unsafe-hashes\' \'nonce-'.base64_encode($policy->getNonceValues()[0]).'\''
            . ' \'sha256-B2yPHKaXnvFWtRChIbabYmUBFZdVfKKXHbWtWidDVF8=\';',
            $header->getFieldValue()
        );
    }

    /**
     * Test rendering a fetch policy with inline allowed.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri 0
     *
     * @return void
     */
    public function testFetchPolicyInlineAllowed(): void
    {
        $policy = new FetchPolicy(
            'script-src',
            false,
            ['https://magento.com'],
            ['https'],
            true,
            true,
            true,
            ['5749837589457695'],
            ['B2yPHKaXnvFWtRChIbabYmUBFZdVfKKXHbWtWidDVF8=' => 'sha256'],
            false,
            false
        );

        $this->renderer->render($policy, $this->response);

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEmpty($this->response->getHeader('Content-Security-Policy'));
        $this->assertEquals(
            'script-src https://magento.com https: \'self\' \'unsafe-inline\' \'unsafe-eval\';',
            $header->getFieldValue()
        );
    }

    /**
     * Test policy rendering in report-only mode with report URL provided.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri /csp-reports/
     *
     * @return void
     */
    public function testRenderReportWithReportingMode(): void
    {
        $policy = new FetchPolicy('default-src', false, ['https://magento.com'], [], true);

        $this->renderer->render($policy, $this->response);

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEmpty($this->response->getHeader('Content-Security-Policy'));
        $this->assertEquals(
            'default-src https://magento.com \'self\'; report-uri /csp-reports/; report-to report-endpoint;',
            $header->getFieldValue()
        );
        $this->assertNotEmpty($endpointsHeader = $this->response->getHeader('Reporting-Endpoints'));
        $this->assertEquals('report-endpoint="/csp-reports/"', $endpointsHeader->getFieldValue());
        $this->assertEmpty($this->response->getHeader('Report-To'));
    }

    /**
     * Test that reporting directives and the endpoint registration are emitted once for several policies.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri https://reports.example.com/csp
     *
     * @return void
     */
    public function testRenderMultiplePoliciesWithReportingMode(): void
    {
        $this->renderer->render(
            new FetchPolicy('default-src', false, ['https://magento.com'], [], true),
            $this->response
        );
        $this->renderer->render(
            new FetchPolicy('script-src', false, ['https://cdn.example.com'], [], true),
            $this->response
        );

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEquals(
            'script-src https://cdn.example.com \'self\';'
            . ' default-src https://magento.com \'self\';'
            . ' report-uri https://reports.example.com/csp; report-to report-endpoint;',
            $header->getFieldValue()
        );
        $this->assertEquals(
            'report-endpoint="https://reports.example.com/csp"',
            $this->response->getHeader('Reporting-Endpoints')->getFieldValue()
        );
        $this->assertEmpty($this->response->getHeader('Report-To'));
    }

    /**
     * Test that a separately configured Reporting API endpoint is registered while report-uri keeps its own URL.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_uri https://reports.example.com/csp
     * @magentoConfigFixture default_store csp/mode/storefront/report_to https://reports.example.com/reporting-api
     *
     * @return void
     */
    public function testRenderWithSeparateReportingEndpoint(): void
    {
        $this->renderer->render(
            new FetchPolicy('default-src', false, ['https://magento.com'], [], true),
            $this->response
        );

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEquals(
            'default-src https://magento.com \'self\';'
            . ' report-uri https://reports.example.com/csp; report-to report-endpoint;',
            $header->getFieldValue()
        );
        $this->assertEquals(
            'report-endpoint="https://reports.example.com/reporting-api"',
            $this->response->getHeader('Reporting-Endpoints')->getFieldValue()
        );
    }

    /**
     * Test that a Reporting API endpoint alone adds report-to without report-uri.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_only 1
     * @magentoConfigFixture default_store csp/mode/storefront/report_to https://reports.example.com/reporting-api
     *
     * @return void
     */
    public function testRenderWithReportingEndpointOnly(): void
    {
        $this->renderer->render(
            new FetchPolicy('default-src', false, ['https://magento.com'], [], true),
            $this->response
        );

        $this->assertNotEmpty($header = $this->response->getHeader('Content-Security-Policy-Report-Only'));
        $this->assertEquals(
            'default-src https://magento.com \'self\'; report-to report-endpoint;',
            $header->getFieldValue()
        );
        $this->assertEquals(
            'report-endpoint="https://reports.example.com/reporting-api"',
            $this->response->getHeader('Reporting-Endpoints')->getFieldValue()
        );
    }

    /**
     * Test that a page with its own report URI keeps its Reporting API reports when the area has an endpoint.
     *
     * @magentoAppArea frontend
     * @magentoConfigFixture default_store csp/mode/storefront/report_to https://reports.example.com/all
     * @magentoConfigFixture default_store csp/mode/storefront_checkout_index_index/report_uri https://r.example/checkout
     *
     * @return void
     */
    public function testRenderPageReportUriBeatsAreaReportingEndpoint(): void
    {
        Bootstrap::getObjectManager()->get(Http::class)
            ->setRouteName('checkout')
            ->setControllerName('index')
            ->setActionName('index');

        $this->renderer->render(
            new FetchPolicy('default-src', false, ['https://magento.com'], [], true),
            $this->response
        );

        $this->assertEquals(
            'default-src https://magento.com \'self\';'
            . ' report-uri https://r.example/checkout; report-to report-endpoint;',
            // Checkout enforces its policy (Magento_Checkout config.xml sets report_only 0 for this page).
            $this->response->getHeader('Content-Security-Policy')->getFieldValue()
        );
        $this->assertEquals(
            'report-endpoint="https://r.example/checkout"',
            $this->response->getHeader('Reporting-Endpoints')->getFieldValue()
        );
    }
}
