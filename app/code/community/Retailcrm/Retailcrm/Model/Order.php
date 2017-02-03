<?php
/**
 * Order class
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
class Retailcrm_Retailcrm_Model_Order extends Retailcrm_Retailcrm_Model_Exchange
{
    /**
     * Order create
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @param mixed $order
     *
     * @return bool
     */
    public function orderPay($orderId)
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        
        if((string)$order->getBaseGrandTotal() == (string)$order->getTotalPaid()){
            $preparedOrder = array(
                'externalId' => $order->getRealOrderId(),//getId(),
                'paymentStatus' => 'paid',
            );
            $preparedOrder = Mage::helper('retailcrm')->filterRecursive($preparedOrder);
            $this->_api->ordersEdit($preparedOrder);
        }
    }
    
    public function orderStatusHistoryCheck($order)
    {
        $config = Mage::getModel(
            'retailcrm/settings',
            array(
                'storeId' =>$order->getStoreId()
            )
        );
        $preparedOrder = array(
            'externalId' => $order->getRealOrderId(),//getId(),
            'status' => $config->getMapping($order->getStatus(), 'status'),
        );
       
       $comment = $order->getStatusHistoryCollection()->getData();
       
       if(!empty($comment[0]['comment'])) {
           $preparedOrder['managerComment'] = $comment[0]['comment'];
       }
       
       $preparedOrder = Mage::helper('retailcrm')->filterRecursive($preparedOrder);
       $this->_api->ordersEdit($preparedOrder);
       
    }
    
    
    public function orderUpdate($order)
    {
        $config = Mage::getModel(
            'retailcrm/settings',
            array(
                'storeId' =>$order->getStoreId()
            )
        );
        $preparedOrder = array(
            'externalId' => $order->getRealOrderId(),//getId(),
            'status' => $config->getMapping($order->getStatus(), 'status'),
        );
        if((float)$order->getBaseGrandTotal() == (float)$order->getTotalPaid()) {
            $preparedOrder['paymentStatus'] = 'paid';
            $preparedOrder = Mage::helper('retailcrm')->filterRecursive($preparedOrder);
            $this->_api->ordersEdit($preparedOrder);
        }
        
    }
    
    public function orderCreate($order)
    {
        $config = Mage::getModel('retailcrm/settings', ['storeId' => $order->getStoreId()]);
        $address = $order->getShippingAddress()->getData();
        $orderItems = $order->getItemsCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('product_type', array('eq'=>'simple'))
            ->load();
        $items = array();

        foreach ($orderItems as $item) {
            if ($item->getProductType() == "simple") {
                if ($item->getParentItemId()) {
                    $parent = Mage::getModel('sales/order_item')->load($item->getParentItemId());
                }

                $product = array(
                    'productId' => $item->getProductId(),
                    'productName' => !isset($parent) ? $item->getName() : $parent->getName(),
                    'quantity' => !isset($parent) ? intval($item->getQtyOrdered()) : intval($parent->getQtyOrdered()),
                    'initialPrice' => !isset($parent) ? $item->getPrice() : $parent->getPrice(),
                    'offer'=>array(
                                'externalId'=>$item->getProductId()
                    )
                );

                unset($parent);
                $items[] = $product;
            } elseif($item->getProductType() == "grouped") {
                $product = array(
                    'productId' => $item->getProductId(),
                    'productName' => $item->getName(),
                    'quantity' => $item->getQtyOrdered(),
                    'initialPrice' => $item->getPrice(),
                    'offer'=>array(
                                'externalId'=>$item->getProductId()
                    )
                );

                $items[] = $product;
            }
        }

        $shipping = $this->getShippingCode($order->getShippingMethod());

        $preparedOrder = array(
            'site' => $order->getStore()->getCode(),
            'externalId' => $order->getRealOrderId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => Mage::getModel('core/date')->date(),
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $address['telephone'],
            'paymentType' => $config->getMapping($order->getPayment()->getMethodInstance()->getCode(), 'payment'),
            'status' => $config->getMapping($order->getStatus(), 'status'),
            'discount' => abs($order->getDiscountAmount()),
            'items' => $items,
            'customerComment' => $order->getStatusHistoryCollection()->getFirstItem()->getComment(),
            'delivery' => array(
                'code' => $config->getMapping($shipping, 'shipping'),
                'cost' => $order->getShippingAmount(),
                'address' => array(
                    'index' => $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id'],
                    'street' => $address['street'],
                    'region' => $address['region'],
                    'text' => trim(
                        ',',
                        implode(
                            ',',
                            array(
                                $address['postcode'],
                                $address['city'],
                                $address['street']
                            )
                        )
                    )
                ),
            )
        );
        
        
        if(trim($preparedOrder['delivery']['code']) == ''){
            unset($preparedOrder['delivery']['code']);
        }
        
        if(trim($preparedOrder['paymentType']) == ''){
            unset($preparedOrder['paymentType']);
        }
        
        if(trim($preparedOrder['status']) == ''){
            unset($preparedOrder['status']);
        }
        
        if ($order->getCustomerIsGuest() == 0) {
            $preparedCustomer = array(
                'externalId' => $order->getCustomerId()
            );
            
            if ($this->_api->customersCreate($preparedCustomer)) {
                $preparedOrder['customer']['externalId'] = $order->getCustomerId();
            }
        }
        
        $preparedOrder = Mage::helper('retailcrm')->filterRecursive($preparedOrder);
        
        Mage::log($preparedOrder, null, 'retailcrmCreatePreparedOrder.log', true);
        
        try {
            $response = $this->_api->ordersCreate($preparedOrder);
            if ($response->isSuccessful() && 201 === $response->getStatusCode()) {
                Mage::log($response->id);
            } else {
                Mage::log(
                    sprintf(
                        "Order create error: [HTTP status %s] %s",
                        $response->getStatusCode(),
                        $response->getErrorMsg()
                    )
                );

                if (isset($response['errors'])) {
                    Mage::log(implode(' :: ', $response['errors']));
                }
            }
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log($e->getMessage());
        }
    }

    public function ordersExportNumber()
    {
        $config = Mage::getStoreConfig('retailcrm');
        $ordersId = explode(",", $config['load_order']['numberOrder']);
        $orders = array();
        
        $ordersList = Mage::getResourceModel('sales/order_collection')
        ->addAttributeToSelect('*')
        ->joinAttribute('billing_firstname', 'order_address/firstname', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_lastname', 'order_address/lastname', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_street', 'order_address/street', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_company', 'order_address/company', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_city', 'order_address/city', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_region', 'order_address/region', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_country', 'order_address/country_id', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_postcode', 'order_address/postcode', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_telephone', 'order_address/telephone', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_fax', 'order_address/fax', 'billing_address_id', null, 'left')
        ->joinAttribute('shipping_firstname', 'order_address/firstname', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_lastname', 'order_address/lastname', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_street', 'order_address/street', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_company', 'order_address/company', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_city', 'order_address/city', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_region', 'order_address/region', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_country', 'order_address/country_id', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_postcode', 'order_address/postcode', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_telephone', 'order_address/telephone', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_fax', 'order_address/fax', 'shipping_address_id', null, 'left')
        ->addAttributeToSort('created_at', 'asc')
        ->setPageSize(1000)
        ->setCurPage(1)
        ->addAttributeToFilter('increment_id', $ordersId)
        ->load();
         
        foreach ($ordersList as $order) {
            $orders[] = $this->prepareOrder($order);
        }
        
        $chunked = array_chunk($orders, 50);
        unset($orders);
        foreach ($chunked as $chunk) {
            $this->_api->ordersUpload($chunk);
            time_nanosleep(0, 250000000);
        }
        
        unset($chunked);
        
        return true;
    
    }
    
    /**
    * Orders export
    *
    * @SuppressWarnings(PHPMD.StaticAccess)
    * @SuppressWarnings(PHPMD.ElseExpression)
    *
    * @return bool
    */
    public function ordersExport()
    {
        $orders = array();
        $ordersList = Mage::getResourceModel('sales/order_collection')
        ->addAttributeToSelect('*')
        ->joinAttribute('billing_firstname', 'order_address/firstname', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_lastname', 'order_address/lastname', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_street', 'order_address/street', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_company', 'order_address/company', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_city', 'order_address/city', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_region', 'order_address/region', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_country', 'order_address/country_id', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_postcode', 'order_address/postcode', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_telephone', 'order_address/telephone', 'billing_address_id', null, 'left')
        ->joinAttribute('billing_fax', 'order_address/fax', 'billing_address_id', null, 'left')
        ->joinAttribute('shipping_firstname', 'order_address/firstname', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_lastname', 'order_address/lastname', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_street', 'order_address/street', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_company', 'order_address/company', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_city', 'order_address/city', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_region', 'order_address/region', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_country', 'order_address/country_id', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_postcode', 'order_address/postcode', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_telephone', 'order_address/telephone', 'shipping_address_id', null, 'left')
        ->joinAttribute('shipping_fax', 'order_address/fax', 'shipping_address_id', null, 'left')
        ->addAttributeToSort('created_at', 'asc')
        ->setPageSize(1000)
        ->setCurPage(1)
        ->load();
        
        foreach ($ordersList as $order) {
            $orders[] = $this->prepareOrder($order);
        }
        
        $chunked = array_chunk($orders, 50);
        unset($orders);
        foreach ($chunked as $chunk) {
            $this->_api->ordersUpload($chunk);
            time_nanosleep(0, 250000000);
        }
        
        unset($chunked);
        
        return true;
    }

    protected function prepareOrder($order)
    {
        $config = Mage::getModel('retailcrm/settings', $order->getStoreId());
        $address = $order->getShippingAddress();
        
        $orderItems = $order->getItemsCollection()
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('product_type', array('eq'=>'simple'))
        ->load();
        $items = array();
        foreach ($orderItems as $item) {
            if ($item->getProductType() == "simple") {
                if ($item->getParentItemId()) {
                    $parent = Mage::getModel('sales/order_item')->load($item->getParentItemId());
                }
                
                $product = array(
                    'productId' => $item->getProductId(),
                    'productName' => !isset($parent) ? $item->getName() : $parent->getName(),
                    'quantity' => !isset($parent) ? intval($item->getQtyOrdered()) : intval($parent->getQtyOrdered()),
                    'initialPrice' => !isset($parent) ? $item->getPrice() : $parent->getPrice()
                );
                unset($parent);
                $items[] = $product;
            }
        }
        
        $shipping = $this->getShippingCode($order->getShippingMethod());
        $preparedOrder = array(
            'externalId' => $order->getRealOrderId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $address['telephone'],
            'paymentType' => $config->getMapping($order->getPayment()->getMethodInstance()->getCode(), 'payment'),
            'status' => $config->getMapping($order->getStatus(), 'status'),
            'discount' => abs($order->getDiscountAmount()),
            'items' => $items,
            'customerComment' => $order->getStatusHistoryCollection()->getFirstItem()->getComment(),
            'delivery' => array(
                'code' => $config->getMapping($shipping, 'shipping'),
                'cost' => $order->getShippingAmount(),
                'address' => array(
                    'index' => $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id'],
                    'street' => $address['street'],
                    'region' => $address['region'],
                    'text' => trim(
                        ',',
                        implode(
                            ',',
                            array(
                                $address['postcode'],
                                $address['city'],
                                $address['street']
                            )
                        )
                    )
                ),
            )
        );
        
        if(trim($preparedOrder['delivery']['code']) == ''){
            unset($preparedOrder['delivery']['code']);
        }
        
        if(trim($preparedOrder['paymentType']) == ''){
            unset($preparedOrder['paymentType']);
        }
        
        if(trim($preparedOrder['status']) == ''){
            unset($preparedOrder['status']);
        }
        
        if ($order->getCustomerIsGuest() != 0) {
            $preparedOrder['customer']['externalId'] = $order->getCustomerId();
        }
        
        return Mage::helper('retailcrm')->filterRecursive($preparedOrder);
    }

    protected function getShippingCode($string)
    {
        $split = array_values(explode('_', $string));
        $length = count($split);
        $prepare = array_slice($split, 0, $length/2);

        return implode('_', $prepare);
    }

    protected function getLocale($code)
    {
        $this->_locale = Mage::app()->getLocale()->getLocaleCode();

        if (!in_array($this->_locale, array('ru_RU', 'en_US'))) {
            $this->_locale = 'en_US';
        }

        $this->_dict = array(
            'ru_RU' => array('sku' => 'Артикул', 'weight' => 'Вес', 'offer' => 'Вариант'),
            'en_US' => array('sku' => 'Sku', 'weight' => 'Weight', 'offer' => 'Offer'),
        );

        return $this->_dict[$this->_locale][$code];
    }
}
