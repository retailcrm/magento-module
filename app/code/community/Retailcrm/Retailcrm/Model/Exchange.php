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
            $this->_api = Mage::getModel(
                'retailcrm/ApiClient',
                array('url' => $this->_apiUrl, 'key' => $this->_apiKey, 'site' => null)
            );
        }
    }

    /**
     * @param mixed $order
     *
     * @return bool
     */
    public function ordersCreate($order)
    {

        $this->_config = Mage::getStoreConfig('retailcrm', $order->getStoreId());

        $statuses = array_flip(array_filter($this->_config['status']));
        $payments = array_filter($this->_config['payment']);
        $shippings = array_filter($this->_config['shipping']);

        $shipment = $order->getShippingMethod();
        $shipment = explode('_', $shipment);
        $shipment = $shipment[0];

        $address = $order->getShippingAddress()->getData();

        $orderItems = $order->getAllItems();
        $simpleItems = array();
        $confItems = array();

        foreach ($orderItems as $item) {
            if ($item->getProductType() == 'simple') {
                $simpleItems[] = $item;
            } else {
                $confItems[$item->getData('item_id')] = $item;
            }
        }

        $items = array();

        foreach ($simpleItems as $item) {

            $product = array(
                'productId' => $item->product_id,
                'productName' => $item->getName(),
                'quantity' => (int) $item->getData('qty_ordered')
            );

            if ($item->getData('parent_item_id')) {
                $product['initialPrice'] = $confItems[$item->getData('parent_item_id')]->getPrice();

                /*if ($confItems[$item->getData('parent_item_id')]->getDiscountAmount() > 0) {
                    $product['discount'] = $confItems[$item->getData('parent_item_id')]->getDiscountAmount();
                }

                if ($confItems[$item->getData('parent_item_id')]->getDiscountPercent() > 0) {
                    $product['discountPercent'] = $confItems[$item->getData('parent_item_id')]->getDiscountPercent();
                }*/
            } else {
                $product['initialPrice'] = $item->getPrice();

                /*if ($item->getDiscountAmount() > 0) {
                    $product['discount'] = $item->getDiscountAmount();
                }

                if ($item->getDiscountPercent() > 0) {
                    $product['discountPercent'] = $item->getDiscountPercent();
                }*/
            }

            $items[] = $product;
        }

        $customer = array(
            'externalId' => ($order->getCustomerIsGuest() == 0) ? $order->getCustomerId() : 'g' . $order->getRealOrderId(),
            'email' => $order->getCustomerEmail(),
            'phone' => $address['telephone'],
            'name' => $order->getCustomerName(),
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
        );

        $customerId = $this->setCustomerId($customer);
        unset($customer);

        $comment = $order->getStatusHistoryCollection()->getFirstItem();
        $preparedOrder = array(
            'site' => $order->getStore()->getCode(),
            'externalId' => $order->getId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'customerId' => $customerId,
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $address['telephone'],
            'paymentType' => $payments[$order->getPayment()->getMethodInstance()->getCode()],
            'status' => $statuses[$order->getStatus()],
            'discount' => abs($order->getDiscountAmount()),
            'items' => $items,
            'customerComment' => $comment->getComment(),
            'delivery' => array(
                'code' => $shippings[$shipment],
                'cost' => $order->getShippingAmount(),
                'address' => array(
                    'index' => $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id'],
                    'street' => $address['street'],
                    'region' => $address['region'],
                    'text' => implode(
                        ',',
                        array(
                            $address['postcode'],
                            $address['country_id'],
                            $address['city'],
                            $address['street']
                        )
                    )
                ),
            )
        );

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

    /**
     * Get orders history & modify orders into shop
     *
     */
    public function ordersHistory()
    {
        $runTime = $this->getExchangeTime($this->_config['general']['history']);

        try {
            $response = $this->_api->ordersHistory($runTime);
            if (
                $response->isSuccessful()
                &&
                200 === $response->getStatusCode()
            ) {
                $nowTime = $response->getGeneratedAt();
                $this->processOrders($response->orders, $nowTime);
            } else {
                Mage::log(
                    sprintf(
                        "Orders history error: [HTTP status %s] %s",
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
        try {
            $response = $this->_api->ordersGet($order['id'], $by = 'id');
            if (
                $response->isSuccessful()
                &&
                200 === $response->getStatusCode()
            ) {
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
        $_store = Mage::getModel("core/store")->load($order['site']);
        $_siteId  = Mage::getModel('core/store')->load($_store->getId())->getWebsiteId();
        $_sendConfirmation = '0';

        // search or create customer
        $customer = Mage::getSingleton('customer/customer');
        $customer->setWebsiteId($_siteId);
        $customer->loadByEmail($order['email']);

        if (!is_numeric($customer->getId())) {
            $customer
                ->setGropuId(1)
                ->setWebsiteId($_siteId)
                ->setStore($_store)
                ->setEmail($order['email'])
                ->setFirstname($order['firstName'])
                ->setLastname($order['lastName'])
                ->setMiddleName($order['patronymic'])
                ->setPassword(uniqid())
            ;

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
                if (
                    !$response->isSuccessful()
                    ||
                    200 !== $response->getStatusCode()
                ) {
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
                'store_id'      => $_store->getId(),
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
                    'street' => $order['customer']['address']['street'],
                    'city' => $order['customer']['address']['city'],
                    'country_id' => $this->getCountryCode($order['customer']['address']['country']),
                    'region' => $order['customer']['address']['region'],
                    'postcode' => $order['customer']['address']['index'],
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

        $quote = Mage::getModel('sales/quote')->setStoreId($_store->getId());
        $quote->assignCustomer($customer);
        $quote->setSendCconfirmation($_sendConfirmation);

        foreach($_products as $idx => $val) {
            $product = Mage::getModel('catalog/product')->load($idx);
            $quote->addProduct($product, new Varien_Object($val));
        }

        $quote->getBillingAddress()->addData($orderData['order']['billing_address']);

        $shippingAddress = $quote->getShippingAddress()->addData($orderData['order']['shipping_address']);
        $shippingAddress
            ->collectTotals()
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($orderData['order']['shipping_method'])
            ->setpaymentMethod($orderData['payment']['method'])
        ;

        $quote->getPayment()->importData($orderData['payment']);
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        try {
            $response = $this->_api->ordersFixExternalIds(
                array(
                    'id' => $order['id'],
                    'externalId' => $service->getOrder()->getId()
                )
            );
            if (
                !$response->isSuccessful()
                ||
                200 !== $response->getStatusCode()
            ) {
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

        Mage::log("Create: " . $order['externalId'], null, 'history.log');
    }

    /**
     * @param array $order
     */
    private function doUpdate($order)
    {
        $magentoOrder = Mage::getModel('sales/order')->load($order['externalId']);

        if (!empty($order['status'])) {
            try {
                $response = $this->_api->statusesList();
                if (
                    $response->isSuccessful()
                    &&
                    200 === $response->getStatusCode()
                ) {
                    $code = $order['status'];
                    $group = $response->statuses[$code]['group'];

                    if (in_array($group, array('approval', 'assembling', 'delivery'))) {
                        $magentoOrder->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                        $magentoOrder->save();

                        $invoice = $magentoOrder->prepareInvoice()
                            ->setTransactionId($magentoOrder->getId())
                            ->register()
                            ->pay();

                        $transaction_save = Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder());

                        $transaction_save->save();
                    }

                    if (in_array($group, array('complete'))) {
                        $itemQty =  $magentoOrder->getItemsCollection()->count();
                        Mage::getModel('sales/service_order', $magentoOrder)->prepareShipment($itemQty);
                        $shipment = new Mage_Sales_Model_Order_Shipment_Api();
                        $shipment->create($order['externalId']);
                    }

                    if (in_array($group, array('cancel'))) {
                        $magentoOrder->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                        $magentoOrder->save();
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
}
