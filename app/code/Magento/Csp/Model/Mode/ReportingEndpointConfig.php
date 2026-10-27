<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Csp\Model\Mode;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * Reporting API endpoint for CSP violation reports, configured apart from the report-uri endpoint.
 *
 * Browsers that support the report-to directive send reports in the Reporting API format, which a collector may
 * accept at a different URL from report-uri reports. The most specific setting wins, as for report-uri: the page's
 * Reporting API endpoint, then the page's own report-uri (null here, so the caller uses it), then the area's
 * Reporting API endpoint.
 */
class ReportingEndpointConfig
{
    /**
     * @param ScopeConfigInterface $config
     * @param Store $storeModel
     * @param State $state
     * @param Http $request
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly Store $storeModel,
        private readonly State $state,
        private readonly Http $request
    ) {
    }

    /**
     * Configured Reporting API endpoint URL for the current page, or null when none is configured
     *
     * @return string|null
     */
    public function getEndpointUrl(): ?string
    {
        $area = $this->state->getAreaCode();
        if ($area === Area::AREA_ADMINHTML) {
            $configArea = 'admin';
        } elseif ($area === Area::AREA_FRONTEND) {
            $configArea = 'storefront';
        } else {
            return null;
        }

        $page = $configArea . '_' . $this->request->getFullActionName();
        $pageEndpoint = $this->getValue($page, 'report_to');
        if ($pageEndpoint !== null || $this->getValue($page, 'report_uri') !== null) {
            // A page with its own reporting URL keeps its Reporting API reports too.
            return $pageEndpoint;
        }

        return $this->getValue($configArea, 'report_to');
    }

    /**
     * Non-empty configured value of csp/mode/<group>/<field> for the current store
     *
     * @param string $group
     * @param string $field
     * @return string|null
     */
    private function getValue(string $group, string $field): ?string
    {
        $value = trim((string)$this->config->getValue(
            'csp/mode/' . $group . '/' . $field,
            ScopeInterface::SCOPE_STORE,
            $this->storeModel->getStore()
        ));

        return $value !== '' ? $value : null;
    }
}
