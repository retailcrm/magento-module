<?php

namespace Retailcrm\Retailcrm\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
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
    const XML_PATH_DAEMON_COLLECTOR = 'daemon_collector/';

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager  = $storeManager;
        parent::__construct($context);
    }

    public function getGeneralSettings($setting = null)
    {
        return $setting === null
            ? $this->getConfigValue(self::XML_PATH_RETAILCRM . 'general')
            : $this->getConfigValue(self::XML_PATH_RETAILCRM . 'general/' . $setting);
    }

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get site code
     *
     * @param $store
     *
     * @return mixed|null
     * @throws \Exception
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

    /**
     * @return array
     */
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
     * @param $website
     *
     * @return array|bool
     */
    public function getDaemonCollector($website)
    {
        $forWebsite = $this->scopeConfig->getValue(
            self::XML_PATH_RETAILCRM . self::XML_PATH_DAEMON_COLLECTOR . 'active',
            ScopeInterface::SCOPE_WEBSITES,
            $website
        );

        if ($forWebsite) {
            return [
                'website' => $website,
                'active' => $forWebsite
            ];
        }

        $forDefault = $this->scopeConfig->getValue(
            self::XML_PATH_RETAILCRM . self::XML_PATH_DAEMON_COLLECTOR . 'active',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        if ($forDefault) {
            return [
                'website' => 0,
                'active' => $forDefault
            ];
        }

        return false;
    }

    public function getInventoriesUpload()
    {
        $inventories = $this->scopeConfig->getValue(
            self::XML_PATH_RETAILCRM . self::XML_PATH_INVENTORIES . 'active',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );

        if ($inventories) {
            return true;
        }

        return false;
    }

    /**
     * @param $website
     *
     * @return bool|mixed
     */
    public function getSiteKey($website)
    {
        $daemonCollector = $this->getDaemonCollector($website);

        if ($daemonCollector === false) {
            return false;
        }

        if ($daemonCollector['active']) {
            if ($daemonCollector['website'] > 0) {
                return $this->scopeConfig->getValue(
                    self::XML_PATH_RETAILCRM . self::XML_PATH_DAEMON_COLLECTOR . 'key',
                    ScopeInterface::SCOPE_WEBSITES,
                    $website
                );
            } else {
                return $this->scopeConfig->getValue(
                    self::XML_PATH_RETAILCRM . self::XML_PATH_DAEMON_COLLECTOR . 'key',
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT
                );
            }
        }

        return false;
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
