<?php

class Retailcrm_Retailcrm_Model_Exchange
{
    protected $_apiKey;
    protected $_apiUrl;
    protected $_config;
    protected $_api;
    
    public function __construct()
    {
        $this->_apiUrl = Mage::getStoreConfig('retailcrm/general/api_url');
        $this->_apiKey = Mage::getStoreConfig('retailcrm/general/api_key');

        if(!empty($this->_apiUrl) && !empty($this->_apiKey)) {
            $this->_api = new Retailcrm_Retailcrm_Model_ApiClient(
                $this->_apiUrl,
                $this->_apiKey
            );
        }
    }
    
/**
 * Get orders history & modify orders into shop
 *
 */
    public function ordersHistory()
    {
        $runTime = $this->getExchangeTime($this->_config['general']['history']);
        $historyFilter = array();
        $historiOrder = array();
        
        $historyStart = Mage::getStoreConfig('retailcrm/general/fhistory');
        if($historyStart && $historyStart > 0) {
            $historyFilter['sinceId'] = $historyStart;
        }
        
        while(true) {
            try {
                $response = $this->_api->ordersHistory($historyFilter);
                if ($response->isSuccessful()&&200 === $response->getStatusCode()) {
                    $nowTime = $response->getGeneratedAt();
                } else {
                        Mage::log(
                            sprintf("Orders history error: [HTTP status %s] %s", $response->getStatusCode(), $response->getErrorMsg())
                        );
    
                        if (isset($response['errors'])) {
                            Mage::log(implode(' :: ', $response['errors']));
                        }
    
                        return false;
                }
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log($e->getMessage());
    
                return false;
            }
    
            $orderH = isset($response['history']) ? $response['history'] : array();
            if(count($orderH) == 0) {
                return true;
            }
    
            $historiOrder = array_merge($historiOrder, $orderH);
            $end = array_pop($response->history);
            $historyFilter['sinceId'] = $end['id'];
    
            if($response['pagination']['totalPageCount'] == 1) {
                Mage::getModel('core/config')->saveConfig('retailcrm/general/fhistory', $historyFilter['sinceId']);
                $orders = self::assemblyOrder($historiOrder);
                $this->processOrders($orders, $nowTime);
    
                return true;
            }
        }//endwhile
    }
    
    /**
     * @param array $orders
     */
    private function processOrders($orders, $time)
    {
        if(!empty($orders)) {
            Mage::getModel('core/config')->saveConfig(
                'retailcrm/general/history', $time
            );

            foreach ($orders as $order) {
                if(!empty($order['externalId'])) {    
                    $this->doUpdate($order);
                } else {
                    $this->doCreate($order);
                }
            }
            
            die();
        }
    }
    
    /**
     * @param array $order
     */
    private function doCreate($order)
    {
        Mage::log($order, null, 'retailcrmHistoriCreate.log', true);
        
        try {
            $response = $this->_api->ordersGet($order['id'], $by = 'id');
          
            if ($response->isSuccessful() && 200 === $response->getStatusCode()) {
                $order = $response->order;
            } else {
                Mage::log(
                    sprintf(
                        "Orders get error: [HTTP status %s] %s",
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
        
        // get references
        $this->_config = Mage::getStoreConfig('retailcrm');
        $payments = array_flip(array_filter($this->_config['payment']));
        $shippings = array_flip(array_filter($this->_config['shipping']));
        
        // get store
        $_sendConfirmation = '0';
        $storeId = Mage::app()->getStore()->getId();
        $siteid = Mage::getModel('core/store')->load($storeId)->getWebsiteId();
        
        // search or create customer
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($siteid);
        $customer->loadByEmail($order['email']);
        
        if (!is_numeric($customer->getId())) {        
            $customer
                ->setGropuId(1)
                ->setWebsiteId($siteid)
                ->setStore($storeId)
                ->setEmail($order['email'])
                ->setFirstname($order['firstName'])
                ->setLastname($order['lastName'])
                ->setMiddleName($order['patronymic'])
                ->setPassword(uniqid());
                
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();
            } catch (Exception $e) {
                Mage::log($e->getMessage());
            }

            $address = Mage::getModel("customer/address");
            $address->setCustomerId($customer->getId())
                ->setFirstname($customer->getFirstname())
                ->setMiddleName($customer->getMiddlename())
                ->setLastname($customer->getLastname())
                ->setCountryId($this->getCountryCode($order['customer']['address']['country']))
                ->setPostcode($order['delivery']['address']['index'])
                ->setCity($order['delivery']['address']['city'])
                ->setRegion($order['delivery']['address']['region'])
                ->setTelephone($order['phone'])
                ->setStreet($order['delivery']['address']['street'])
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('1')
                ->setSaveInAddressBook('1');

            try{
                $address->save();
            }
            catch (Exception $e) {
                Mage::log($e->getMessage());
            }

            try {
                $response = $this->_api->customersFixExternalIds(
                    array(
                        'id' => $order['customer']['id'],
                        'externalId' => $customer->getId()
                    )
                );
                if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
                    Mage::log(
                        sprintf(
                            "Orders fix error: [HTTP status %s] %s",
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

        $products = array();
        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = array('qty' => $item['quantity']);
        }
        
        $orderData = array(
            'session'       => array(
                'customer_id'   => $customer->getId(),
                'store_id'      => $storeId,
            ),
            'payment'       => array(
                'method'    => $payments[$order['paymentType']],
            ),
            'add_products'  => $products,
            'order' => array(
                'account' => array(
                    'group_id' => $customer->getGroupId(),
                    'email' => $order['email']
                ),
                'billing_address' => array(
                    'firstname' => $order['firstName'],
                    'middlename' => $order['patronymic'],
                    'lastname' => $order['lastName'],
                    'street' => $order['delivery']['address']['street'],
                    'city' => $order['delivery']['address']['city'],
                    'country_id' => $this->getCountryCode($order['customer']['address']['country']),
                    'region' => $order['delivery']['address']['region'],
                    'postcode' => $order['delivery']['address']['index'],
                    'telephone' => $order['phone'],
                ),
                'shipping_address' => array(
                    'firstname' => $order['firstName'],
                    'middlename' => $order['patronymic'],
                    'lastname' => $order['lastName'],
                    'street' => $order['delivery']['address']['street'],
                    'city' => $order['delivery']['address']['city'],
                    'country_id' => $this->getCountryCode($order['customer']['address']['country']),
                    'region' => $order['delivery']['address']['region'],
                    'postcode' => $order['delivery']['address']['index'],
                    'telephone' => $order['phone'],
                ),
                'shipping_method' => $shippings[$order['delivery']['code']],
                'comment' => array(
                    'customer_note' => $order['customerComment'],
                ),
                'send_confirmation' => $_sendConfirmation
            )
        );

        Mage::unregister('sales_order_place_after');
        Mage::register('sales_order_place_after', 1);

        $quote = Mage::getModel('sales/quote')->setStoreId($storeId);
        $quote->assignCustomer($customer);
        $quote->setSendCconfirmation($_sendConfirmation);
        
        foreach($products as $idx => $val) {
            $product = Mage::getModel('catalog/product')->load($idx);
            $quote->addProduct($product, new Varien_Object($val));
        }
        
        $shipping_method = self::getAllShippingMethodsCode($orderData['order']['shipping_method']);
        $billingAddress = $quote->getBillingAddress()->addData($orderData['order']['billing_address']);
        $shippingAddress = $quote->getShippingAddress()->addData($orderData['order']['shipping_address']);
                
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shipping_method)
            ->setPaymentMethod($orderData['payment']['method']);
    
        $quote->getPayment()->importData($orderData['payment']);
        $quote->collectTotals();
        $quote->reserveOrderId();
        $quote->save();
        
        $service = Mage::getModel('sales/service_quote', $quote);
        
        try{
            $service->submitAll();
        }
        catch (Exception $e) {
            Mage::log($e->getMessage());
        }
        
        try {
            $response = $this->_api->ordersFixExternalIds(
                array(
                    array(
                        'id' => $order['id'], 
                        'externalId' =>$service->getOrder()->getRealOrderId()
                    )
                )
            );
                
            if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
                Mage::log(
                    sprintf(
                        "Orders fix error: [HTTP status %s] %s",
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

    /**
     * @param array $order
     */
    private function doCreateUp($order)
    {
        Mage::log($order, null, 'retailcrmHistoriCreateUp.log', true);
    
        try {
            $response = $this->_api->ordersGet($order['id'], $by = 'id');
    
            if ($response->isSuccessful() && 200 === $response->getStatusCode()) {
                        $order = $response->order;
            } else {
                    Mage::log(
                        sprintf(
                            "Orders get error: [HTTP status %s] %s",
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
        
        // get references
        $this->_config = Mage::getStoreConfig('retailcrm');
        $payments = array_flip(array_filter($this->_config['payment']));
        $shippings = array_flip(array_filter($this->_config['shipping']));
     
        // get store
        $_sendConfirmation = '0';
        $storeId = Mage::app()->getStore()->getId();
        $siteid = Mage::getModel('core/store')->load($storeId)->getWebsiteId();
    
        // search or create customer
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($siteid);
        $customer->loadByEmail($order['email']);
     
        if (!is_numeric($customer->getId())) {  
            $customer
            ->setGropuId(1)
            ->setWebsiteId($siteid)
            ->setStore($storeId)
            ->setEmail($order['email'])
            ->setFirstname($order['firstName'])
            ->setLastname($order['lastName'])
            ->setMiddleName($order['patronymic'])
            ->setPassword(uniqid());
    
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();
            } catch (Exception $e) {
                Mage::log($e->getMessage());
            }
    
            $address = Mage::getModel("customer/address");
            $address->setCustomerId($customer->getId())
            ->setFirstname($customer->getFirstname())
            ->setMiddleName($customer->getMiddlename())
            ->setLastname($customer->getLastname())
            ->setCountryId($this->getCountryCode($order['customer']['address']['country']))
            ->setPostcode($order['delivery']['address']['index'])
            ->setCity($order['delivery']['address']['city'])
            ->setRegion($order['delivery']['address']['region'])
            ->setTelephone($order['phone'])
            ->setStreet($order['delivery']['address']['street'])
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('1');
    
            try{
                $address->save();
            }
            catch (Exception $e) {
                Mage::log($e->getMessage());
            }
    
            try {
                $response = $this->_api->customersFixExternalIds(
                    array(
                            'id' => $order['customer']['id'],
                            'externalId' => $customer->getId()
                    )
                );
                if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
                       Mage::log(
                           sprintf(
                               "Orders fix error: [HTTP status %s] %s",
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
    
        $products = array();
        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = array('qty' => $item['quantity']);
        }
    
        $orderData = array(
                'session'       => array(
                        'customer_id'   => $customer->getId(),
                        'store_id'      => $storeId,
                ),
                'payment'       => array(
                        'method'    => $payments[$order['paymentType']],
                ),
                'add_products'  => $products,
                'order' => array(
                        'account' => array(
                                'group_id' => $customer->getGroupId(),
                                'email' => $order['email']
                        ),
                        'billing_address' => array(
                                'firstname' => $order['firstName'],
                                'middlename' => $order['patronymic'],
                                'lastname' => $order['lastName'],
                                'street' => $order['delivery']['address']['street'],
                                'city' => $order['delivery']['address']['city'],
                                'country_id' => $this->getCountryCode($order['customer']['address']['country']),
                                'region' => $order['delivery']['address']['region'],
                                'postcode' => $order['delivery']['address']['index'],
                                'telephone' => $order['phone'],
                        ),
                        'shipping_address' => array(
                                'firstname' => $order['firstName'],
                                'middlename' => $order['patronymic'],
                                'lastname' => $order['lastName'],
                                'street' => $order['delivery']['address']['street'],
                                'city' => $order['delivery']['address']['city'],
                                'country_id' => $this->getCountryCode($order['customer']['address']['country']),
                                'region' => $order['delivery']['address']['region'],
                                'postcode' => $order['delivery']['address']['index'],
                                'telephone' => $order['phone'],
                        ),
                        'shipping_method' => $shippings[$order['delivery']['code']],
                        'comment' => array(
                                'customer_note' => $order['customerComment'],
                        ),
                        'send_confirmation' => $_sendConfirmation
                )
        );
         
        $quote = Mage::getModel('sales/quote')->setStoreId($storeId);
        $quote->assignCustomer($customer);
        $quote->setSendCconfirmation($_sendConfirmation);
    
        foreach($products as $idx => $val) {
            $product = Mage::getModel('catalog/product')->load($idx);
            $quote->addProduct($product, new Varien_Object($val));
        }

        $shipping_method = self::getAllShippingMethodsCode($orderData['order']['shipping_method']);
        $billingAddress = $quote->getBillingAddress()->addData($orderData['order']['billing_address']);
        $shippingAddress = $quote->getShippingAddress()->addData($orderData['order']['shipping_address']);
    
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shipping_method)
            ->setPaymentMethod($orderData['payment']['method']);
    
        $quote->getPayment()->importData($orderData['payment']);
        $quote->collectTotals();
    
        $originalId = $order['externalId'];
        $oldOrder = Mage::getModel('sales/order')->loadByIncrementId($originalId);
        $oldOrderArr = $oldOrder->getData();
    
        if(!empty($oldOrderArr['original_increment_id'])) {
            $originalId = $oldOrderArr['original_increment_id'];
        }
    
        $orderDataUp = array(
                'original_increment_id'     => $originalId,
                'relation_parent_id'        => $oldOrder->getId(),
                'relation_parent_real_id'   => $oldOrder->getIncrementId(),
                'edit_increment'            => $oldOrder->getEditIncrement()+1,
                'increment_id'              => $originalId.'-'.($oldOrder->getEditIncrement()+1)
        );
    
        $quote->setReservedOrderId($orderDataUp['increment_id']);
        $quote->save();
    
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->setOrderData($orderDataUp);
    
        try{
            $service->submitAll();
        }
        catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    
        $magentoOrder = Mage::getModel('sales/order')->loadByIncrementId($orderDataUp['relation_parent_real_id']);
        $magentoOrder->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
    
        try {
            $response = $this->_api->ordersFixExternalIds(
                array(
                    array(
                            'id' => $order['id'],
                            'externalId' =>$service->getOrder()->getRealOrderId()
                    )
                )
            );
    
            if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
                Mage::log(
                    sprintf(
                        "Orders fix error: [HTTP status %s] %s",
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
    
    /**
     * @param array $order
     */
    private function doUpdate($order)
    {  
        $magentoOrder = Mage::getModel('sales/order')->loadByIncrementId($order['externalId']);
        $magentoOrderArr = $magentoOrder->getData();
        $config = Mage::getStoreConfig('retailcrm');
        
        Mage::log($order, null, 'retailcrmHistoriUpdate.log', true);
        
        if((!empty($order['order_edit']))&&($order['order_edit'] == 1)) {
           $this->doCreateUp($order);
        }
        
        if (!empty($order['status'])) {
                 try {
                    $response = $this->_api->statusesList();
           
                    if ($response->isSuccessful() && 200 === $response->getStatusCode()) {
                        $code = $order['status'];   
                        $group = $response->statuses[$code]['group'];
                    
                        if ($magentoOrder->hasInvoices()) {
                            $invIncrementIDs = array();
                            foreach ($magentoOrder->getInvoiceCollection() as $inv) {
                                $invIncrementIDs[] = $inv->getIncrementId();
                            }
                        }
                    
                        if (in_array($group, array('approval', 'assembling', 'delivery'))) {
                            if(empty($invIncrementIDs)) {
                                $magentoOrder->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                                $magentoOrder->save();
    
                                $invoice = $magentoOrder->prepareInvoice()
                                    ->setTransactionId($magentoOrder->getRealOrderId())
                                    ->addComment("Add status on CRM")
                                    ->register()
                                    ->pay();
    
                                $transaction_save = Mage::getModel('core/resource_transaction')
                                    ->addObject($invoice)
                                    ->addObject($invoice->getOrder());
    
                                $transaction_save->save();
                            }
                        }
                    
                        if (in_array($group, array('complete'))) {
                            if(empty($invIncrementIDs)){
                                $invoice = $magentoOrder->prepareInvoice()
                                    ->setTransactionId($magentoOrder->getRealOrderId())
                                    ->addComment("Add status on CRM")
                                    ->register()
                                    ->pay();
    
                                $transaction_save = Mage::getModel('core/resource_transaction')
                                    ->addObject($invoice)
                                    ->addObject($invoice->getOrder());
                                
                                $transaction_save->save();
                                $magentoOrder->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
                            }
                    
                            if($magentoOrder->canShip()) {
                                $itemQty =  $magentoOrder->getItemsCollection()->count();
                                $shipment = Mage::getModel('sales/service_order', $magentoOrder)->prepareShipment($itemQty);
                                $shipment = new Mage_Sales_Model_Order_Shipment_Api();
                                $shipmentId = $shipment->create($order['externalId']);
                            }
                        }

                        if($code == $config['status']['canceled']) { 
                            $magentoOrder->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
                        }
                    
                        if($code == $config['status']['holded']) {
                            if($magentoOrder->canHold()){
                                $magentoOrder->hold()->save();
                            }
                        }
                    
                        if($code == $config['status']['unhold']) {
                            if($magentoOrder->canUnhold()) {
                                $magentoOrder->unhold()->save();
                            }
                        }
                        
                        if($code == $config['status']['closed']) {
                            if($magentoOrder->canCreditmemo()) {
                                $orderItem = $magentoOrder->getItemsCollection();
                                foreach ($orderItem as $item) {
                                    $data['qtys'][$item->getid()] = $item->getQtyOrdered();
                                }    
                                
                                $service = Mage::getModel('sales/service_order', $magentoOrder);
                                $creditMemo = $service->prepareCreditmemo($data)->register()->save();
                                $magentoOrder->addStatusToHistory(Mage_Sales_Model_Order::STATE_CLOSED, 'Add status on CRM', false);
                                $magentoOrder->save();
                            }
                        }
                    
                        Mage::log("Update: " . $order['externalId'], null, 'history.log');
                    } else {
                        Mage::log(
                            sprintf(
                                "Statuses list error: [HTTP status %s] %s",
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
        
        if(!empty($order['manager_comment'])) {
            $magentoOrder->addStatusHistoryComment($order['manager_comment']);
            $magentoOrder->save();
        }
       
    }

    /**
     * @param $customer
     * @return mixed
     */
    private function setCustomerId($customer)
    {
        $customerId = $this->searchCustomer($customer);

        if (is_array($customerId) && !empty($customerId)) {
            if ($customerId['success']) {
                return $customerId['result'];
            } else {
                $this->fixCustomer($customerId['result'], $customer['externalId']);
                return $customer['externalId'];
            }
        } else {
            $this->createCustomer(
                array(
                    'externalId' => $customer['externalId'],
                    'firstName' => $customer['firstName'],
                    'lastName' => isset($customer['lastName']) ? $customer['lastName'] : '',
                    'patronymic' => isset($customer['patronymic']) ? $customer['patronymic'] : '',
                    'phones' => isset($customer['phone']) ? array($customer['phone']) : array(),
                )
            );

            return $customer['externalId'];
        }
    }

    /**
     * @param $data
     * @return array|bool
     */
    private function searchCustomer($data)
    {
        try {
            $customers = $this->_api->customersList(
                array(
                    'name' => isset($data['phone']) ? $data['phone'] : $data['name'],
                    'email' => $data['email']
                ),
                1,
                100
            );
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log($e->getMessage());
        }

        if ($customers->isSuccessful()) {
            return (count($customers['customers']) > 0)
                ? $this->defineCustomer($customers['customers'])
                : false
                ;
        }
    }

    private function createCustomer($customer)
    {
        try {
            $this->_api->customersCreate($customer);
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log('RestApi::CustomersCreate::Curl: ' . $e->getMessage());
        }
    }

    private function fixCustomer($id, $extId)
    {
        try {
            $this->_api->customersFixExternalIds(
                array(
                    array(
                        'id' => $id,
                        'externalId' => $extId
                    )
                )
            );
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log('RestApi::CustomersFixExternalIds::Curl: ' . $e->getMessage());
        }
    }

    private function defineCustomer($searchResult)
    {
        $result = '';
        foreach ($searchResult as $customer) {
            if (isset($customer['externalId']) && $customer['externalId'] != '') {
                $result = $customer['externalId'];
                break;
            }
        }

        return ($result != '')
            ? array('success' => true, 'result' => $result)
            : array('success' => false, 'result' => $searchResult[0]['id']);
    }

    private function getExchangeTime($datetime)
    {
        if (empty($datetime)) {
            $datetime = new DateTime(
                date(
                    'Y-m-d H:i:s',
                    strtotime('-1 days', strtotime(date('Y-m-d H:i:s')))
                )
            );
        } else {
            $datetime = new DateTime($datetime);
        }

        return $datetime;
    }

    private function getCountryCode($string)
    {
        $country = empty($string) ? 'RU' : $string;
        $xmlObj = new Varien_Simplexml_Config(Mage::getModuleDir('etc', 'Retailcrm_Retailcrm').DS.'country.xml');
        $xmlData = $xmlObj->getNode();

        if ($country != 'RU') {
            foreach ($xmlData as $elem) {
                if ($elem->name == $country || $elem->english == $country) {
                    $country = $elem->alpha2;
                    break;
                }
            }
        }

        return (string) $country;
    }
    
    
    public static function assemblyOrder($orderHistory)
    {
        $orders = array();
        foreach ($orderHistory as $change) {
            $change['order'] = self::removeEmpty($change['order']);
            if($change['order']['items']) {
                $items = array();
                foreach($change['order']['items'] as $item) {
                    if(isset($change['created'])) {
                        $item['create'] = 1;
                    }
    
                    $items[$item['id']] = $item;
                }
                
                $change['order']['items'] = $items;
            }
    
            Mage::log($change, null, 'retailcrmHistoryAssemblyOrder.log', true);
   
            if($change['order']['contragent']['contragentType']) {
                $change['order']['contragentType'] = self::newValue($change['order']['contragent']['contragentType']);
                unset($change['order']['contragent']);
            }
    
            if($orders[$change['order']['id']]) {
                $orders[$change['order']['id']] = array_merge($orders[$change['order']['id']], $change['order']);
            }
    
            else {
                $orders[$change['order']['id']] = $change['order'];
            }
    
            if($change['field'] == 'manager_comment'){
                $orders[$change['order']['id']][$change['field']] = $change['newValue'];
            }
    
            if(($change['field'] != 'status')&&
                ($change['field'] != 'country')&&
                ($change['field'] != 'manager_comment')&&
                ($change['field'] != 'order_product.status')&&
                ($change['field'] != 'payment_status')&&
                ($change['field'] != 'prepay_sum')
            ) {
                $orders[$change['order']['id']]['order_edit'] = 1;
            }
   
            if($change['item']) {         
                if($orders[$change['order']['id']]['items'][$change['item']['id']]) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = array_merge($orders[$change['order']['id']]['items'][$change['item']['id']], $change['item']);
                }
    
                else{
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = $change['item'];
                }
    
                if(empty($change['oldValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['create'] = 1;
                    $orders[$change['order']['id']]['order_edit'] = 1;
                    unset($orders[$change['order']['id']]['items'][$change['item']['id']]['delete']);
                }
    
                if(empty($change['newValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['delete'] = 1;
                    $orders[$change['order']['id']]['order_edit'] = 1; 
                }
    
                if(!empty($change['newValue']) && $change['field'] == 'order_product.quantity') {
                    $orders[$change['order']['id']]['order_edit'] = 1;
                }
   
                if(!$orders[$change['order']['id']]['items'][$change['item']['id']]['create'] && $fields['item'][$change['field']]) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']][$fields['item'][$change['field']]] = $change['newValue'];
                }
            }
            else {
                if($fields['delivery'][$change['field']] == 'service') {
                    $orders[$change['order']['id']]['delivery']['service']['code'] = self::newValue($change['newValue']);
                }
                elseif($fields['delivery'][$change['field']]) {
                    $orders[$change['order']['id']]['delivery'][$fields['delivery'][$change['field']]] = self::newValue($change['newValue']);
                }
                elseif($fields['orderAddress'][$change['field']]) {
                    $orders[$change['order']['id']]['delivery']['address'][$fields['orderAddress'][$change['field']]] = $change['newValue'];
                }
                elseif($fields['integrationDelivery'][$change['field']]) {
                    $orders[$change['order']['id']]['delivery']['service'][$fields['integrationDelivery'][$change['field']]] = self::newValue($change['newValue']);
                }
                elseif($fields['customerContragent'][$change['field']]) {
                    $orders[$change['order']['id']][$fields['customerContragent'][$change['field']]] = self::newValue($change['newValue']);
                }
                elseif(strripos($change['field'], 'custom_') !== false) {
                    $orders[$change['order']['id']]['customFields'][str_replace('custom_', '', $change['field'])] = self::newValue($change['newValue']);
                }
                elseif($fields['order'][$change['field']]) {
                    $orders[$change['order']['id']][$fields['order'][$change['field']]] = self::newValue($change['newValue']);
                }
    
                if(isset($change['created'])) {
                    $orders[$change['order']['id']]['create'] = 1;
                }
    
                if(isset($change['deleted'])) {
                    $orders[$change['order']['id']]['deleted'] = 1;
                }
            }
        }

        return $orders;
    }
    
    public static function removeEmpty($inputArray)
    {
        $outputArray = array();
        if (!empty($inputArray)) {
            foreach ($inputArray as $key => $element) {
                if(!empty($element) || $element === 0 || $element === '0') {
                    if (is_array($element)) {
                        $element = self::removeEmpty($element);
                    }
                    
                    $outputArray[$key] = $element;
                }
            }
        }
    
        return $outputArray;
    }
    
    public static function newValue($value)
    {
        if(isset($value['code'])) {
            return $value['code'];
        } else{
            return $value;
        }
    }
    
    public static function getAllShippingMethodsCode($code)
    {
        $methods = Mage::getSingleton('shipping/config')->getActiveCarriers();
        $options = array();
        foreach($methods as $_ccode => $_carrier) {
            if($_methods = $_carrier->getAllowedMethods()) {
                foreach($_methods as $_mcode => $_method) {
                    $_code = $_ccode . '_' . $_mcode;
                    $options[$_ccode] = $_code;
                }
            }            
        }
    
        return $options[$code];
    }
    
    
}

