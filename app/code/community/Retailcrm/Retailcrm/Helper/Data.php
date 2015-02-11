<?php
/**
 * Data helper
 *
 * @author RetailCRM
 */
class Retailcrm_Retailcrm_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Path to store config if front-end output is enabled
     *
     * @var string
     */
    const XML_API_URL = 'retailcrm/general/api_url';

    /**
     * Path to store config where count of news posts per page is stored
     *
     * @var string
     */
    const XML_API_KEY = 'retailcrm/general/api_key';

    /**
     * Return api url
     *
     * @param integer|string|Mage_Core_Model_Store $store
     * @return int
     */
    public function getApiUrl($store = null)
    {
        return abs((int)Mage::getStoreConfig(self::XML_API_URL, $store));
    }

    /**
     * Return api key
     *
     * @param integer|string|Mage_Core_Model_Store $store
     * @return int
     */
    public function getApiKey($store = null)
    {
        return abs((int)Mage::getStoreConfig(self::XML_API_KEY, $store));
    }

    public function rewrittenProductUrl($productId, $categoryId, $storeId)
    {
        $coreUrl = Mage::getModel('core/url_rewrite');
        $idPath = sprintf('product/%d', $productId);
        if ($categoryId) {
            $idPath = sprintf('%s/%d', $idPath, $categoryId);
        }
        $coreUrl->setStoreId($storeId);
        $coreUrl->loadByIdPath($idPath);

        return Mage::getBaseUrl( Mage_Core_Model_Store::URL_TYPE_WEB, true ) . $coreUrl->getRequestPath();
    }

}
