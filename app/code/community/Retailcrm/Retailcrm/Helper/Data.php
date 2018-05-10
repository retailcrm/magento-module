<?php
/**
 * Default extension helper class
 * PHP version 5.3
 *
 * @category Model
 * @package  RetailCrm\Model
 * @author   RetailDriver LLC <integration@retailcrm.ru>
 * @license  http://opensource.org/licenses/MIT MIT License
 * @link     http://www.magentocommerce.com/magento-connect/retailcrm-1.html
 */

/**
 * Data helper class
 *
 * @category Model
 * @package  RetailCrm\Model
 * @author   RetailDriver LLC <integration@retailcrm.ru>
 * @license  http://opensource.org/licenses/MIT MIT License
 * @link     http://www.magentocommerce.com/magento-connect/retailcrm-1.html
 *
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
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
     * @var string
     */
    const XML_SITES = 'retailcrm/sites/';

    /**
     * @var string
     */
    const XML_SITE = 'retailcrm/site/default';


    /**
     * Get site code
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return mixed|null
     */
    public function getSite($store)
    {
        if (!$store instanceof Mage_Core_Model_Store
            && is_int($store)
        ) {
            $store = Mage::app()->getStore($store);
        }

        $website = $store->getWebsite();
        $site = $website->getConfig(self::XML_SITES . $store->getCode());

        if ($site) {
            return $site;
        } else {
            $site = Mage::getStoreConfig(self::XML_SITE);

            if ($site) {
                return $site;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getMappingSites() {
        $sites = array();
        $webSites = Mage::app()->getWebsites();

        foreach ($webSites as $webSite) {
            $storesFromSite = $webSite->getStores();

            foreach ($storesFromSite as $store) {
                $config = $webSite->getConfig(self::XML_SITES . $store->getCode());

                if ($config) {
                    $sites[$config] = $store->getCode();
                }
            }

            unset($storesFromSite);
        }

        unset($webSites);

        return $sites;
    }

    /**
      * Get api url
      *
      * @param Mage_Core_Model_Store $store store instance
      *
      * @SuppressWarnings(PHPMD.StaticAccess)
      *
      * @return int
      */
    public function getApiUrl($store = null)
    {
        return abs((int)Mage::getStoreConfig(self::XML_API_URL, $store));
    }

     /**
      * Get api key
      *
      * @param Mage_Core_Model_Store $store store instance
      *
      * @SuppressWarnings(PHPMD.StaticAccess)
      *
      * @return int
      */
    public function getApiKey($store = null)
    {
        return abs((int)Mage::getStoreConfig(self::XML_API_KEY, $store));
    }

    /**
     * Get api key
     *
     * @param string  $baseUrl    base url
     * @param mixed   $coreUrl    url rewritte
     * @param integer $productId  product id
     * @param integer $storeId    product store id
     * @param integer $categoryId product category id
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return string
     */
    public function rewrittenProductUrl($baseUrl,$coreUrl,$productId,$storeId,$categoryId = null)
    {
        $idPath = sprintf('product/%d', $productId);
        if ($categoryId) {
             $idPath = sprintf('%s/%d', $idPath, $categoryId);
        }
    
        $coreUrl->setStoreId($storeId);
        $coreUrl->loadByIdPath($idPath);

        return $baseUrl . $coreUrl->getRequestPath();
    }

    /**
     * Get country code
     *
     * @param string $string country iso code
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return string
     */
    public function getCountryCode($string)
    {
        $country = empty($string) ? 'RU' : $string;
        $xmlObj = new Varien_Simplexml_Config(
            sprintf(
                "%s%s%s",
                Mage::getModuleDir('etc', 'Retailcrm_Retailcrm'),
                DS,
                'country.xml'
            )
        );
        $xmlData = $xmlObj->getNode();

        if ($country != 'RU') {
            foreach ($xmlData as $elem) {
                if ($elem->name == $country || $elem->english == $country) {
                    $country = $elem->alpha;
                    break;
                }
            }
        }

        return (string) $country;
    }

    /**
     * Get exchage time
     *
     * @param string $datetime datetime string
     *
     * @return \DateTime
     */
    public function getExchangeTime($datetime)
    {
        return $datetime = empty($datetime)
            ? new DateTime(
                date(
                    'Y-m-d H:i:s',
                    strtotime('-1 days', strtotime(date('Y-m-d H:i:s')))
                )
            )
            : new DateTime($datetime);
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
    public function filterRecursive($haystack)
    {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                $haystack[$key] = self::filterRecursive($haystack[$key]);
            }
            
            if (is_null($haystack[$key])
                || $haystack[$key] === ''
                || count($haystack[$key]) == 0
            ) {
                unset($haystack[$key]);
            } elseif (!is_array($value)) {
                $haystack[$key] = trim($value);
            }
        }

        return $haystack;
    }
}
