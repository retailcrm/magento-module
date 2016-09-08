<?php
/**
 * Settings class
 *
 * @category Model
 * @package  RetailCrm\Model
 * @author   RetailDriver LLC <integration@retailcrm.ru>
 * @license  http://opensource.org/licenses/MIT MIT License
 * @link     http://www.magentocommerce.com/magento-connect/retailcrm-1.html
 *
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Retailcrm_Retailcrm_Model_Settings
{
    protected $_config;
    protected $_storeDefined;

    /**
     * Constructor
     *
     * @param array $params
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ElseExpression)
     *
     * @return bool
     */
     public function __construct(array $params = array())
     {
         $this->_config = empty($params)
            ? $this->setConfigWithoutStoreId()
            : $this->setConfigWithStoreId($params['storeId']);
     }

     /**
      * Get mapping values
      *
      * @param string $code
      * @param string $type (default: status, values: status, payment, shipping)
      * @param bool   $reverse
      *
      * @SuppressWarnings(PHPMD.StaticAccess)
      * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
      *
      * @return mixed
      */
      public function getMapping($code, $type, $reverse = false)
      {
          if (!in_array($type, array('status', 'payment', 'shipping'))) {
              throw new \InvalidArgumentException(
                  "Parameter 'type' must be 'status', 'payment' or 'shipping'"
              );
          }

          $array = ($reverse)
            ? array_flip(array_filter($this->_config[$type]))
            : array_filter($this->_config[$type]);

          return array_key_exists($code, $array)
            ? $array[$code]
            : false;
      }

      /**
       * Set config with orderStoreId
       *
       * @param string $storeId
       *
       * @SuppressWarnings(PHPMD.StaticAccess)
       *
       * @return mixed
       */
       protected function setConfigWithStoreId($storeId)
       {
           $this->_storeDefined = true;
           return Mage::getStoreConfig('retailcrm', $storeId);
       }

       /**
        * Set config without orderStoreId
        *
        * @SuppressWarnings(PHPMD.StaticAccess)
        *
        * @return mixed
        */
        protected function setConfigWithoutStoreId()
        {
            $this->_storeDefined = false;
            return Mage::getStoreConfig('retailcrm');
        }
}
