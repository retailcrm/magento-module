<?php

namespace Retailcrm\Retailcrm\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private $storeManager;

    const XML_PATH_RETAILCRM = 'retailcrm/';
    const XML_PATH_DEFAULT_SITE = 'retailcrm_site/default';
    const XML_PATH_SITES = 'retailcrm_sites/';

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager  = $storeManager;
        parent::__construct($context);
    }

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_RETAILCRM . $code, $storeId);
    }

    /**
     * Get site code
     *
     * @param $store
     *
     * @return mixed|null
     */
    public function getSite($store)
    {
        if (is_int($store)) {
            $store = $this->storeManager->getStore($store);
        }

        $websitesConfig = $this->scopeConfig->getValue(
            self::XML_PATH_RETAILCRM . self::XML_PATH_SITES . $store->getCode(),
            ScopeInterface::SCOPE_WEBSITES
        );

        if (!$websitesConfig) {
            $defaultSite = $this->scopeConfig->getValue(self::XML_PATH_RETAILCRM . self::XML_PATH_DEFAULT_SITE);

            if (!$defaultSite) {
                return null;
            }

            return $defaultSite;
        }

        return $websitesConfig;
    }

    public function getMappingSites()
    {
        $sites = [];

        $websites = $this->storeManager->getWebsites();

        foreach ($websites as $website) {
            foreach ($website->getStores() as $store) {
                $site = $this->scopeConfig->getValue(
                    self::XML_PATH_RETAILCRM . self::XML_PATH_SITES . $store->getCode(),
                    ScopeInterface::SCOPE_WEBSITES,
                    $website->getId()
                );
                $sites[$site] = $store->getId();
            }
        }

        return $sites;
    }

    /**
     * Recursive array filter
     *
     * @param array $haystack input array
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ElseExpression)
     *
     * @return array
     */
    public static function filterRecursive($haystack)
    {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                $haystack[$key] = self::filterRecursive($haystack[$key]);
            }

            if ($haystack[$key] === null
                || $haystack[$key] === ''
                || (is_array($haystack[$key]) && empty($haystack[$key]))
            ) {
                unset($haystack[$key]);
            } elseif (!is_array($value)) {
                $haystack[$key] = trim($value);
            }
        }

        return $haystack;
    }
}
