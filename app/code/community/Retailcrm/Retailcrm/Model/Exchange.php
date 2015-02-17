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
    public function orderCreate($order)
    {
        $this->_config = Mage::getStoreConfig('retailcrm', $order->getStoreId());

        $statuses = array_flip(array_filter($this->_config['status']));
        $paymentsStatuses = array_flip(array_filter($this->_config['paymentstatus']));
        $payments = array_filter($this->_config['payment']);
        $shippings = array_filter($this->_config['shipping']);

        $address = $order->getShippingAddress()->getData();

        $orderItems = $order->getItemsCollection();
        $items = array();

        foreach ($orderItems as $item){
            $items[] = array(
                'productId' => $item->product_id,
                'initialPrice' => $item->getPrice(),
                'taxPrice' => $item->getPriceInclTax(),
                'productName' => $item->getName(),
                'quantity' => (int) $item->getData('qty_ordered')
            );
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
            'paymentStatus' => $paymentsStatuses[$order->getStatus()],
            'status' => $statuses[$order->getStatus()],
            'discount' => abs($order->getDiscountAmount()),
            'items' => $items,
            'delivery' => array(
                'code' => $shippings[$order->getShippingMethod()],
                'cost' => $order->getShippingAmount(),
                'address' => array(
                    'index' => $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id'],
                    'street' => $address['street'],
                    'region' => $address['region'],

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


    /**
     * @param $data
     * @return bool
     * @internal param mixed $order
     *
     */
    /*public function orderEdit($order)
    {
        $this->_config = Mage::getStoreConfig('retailcrm', $order->getStoreId());

        $statuses = array_flip(array_filter($this->_config['status']));
        $paymentsStatuses = array_flip(array_filter($this->_config['paymentstatuses']));
        $payments = array_filter($this->_config['payment']);
        $shippings = array_filter($this->_config['shipping']);

        $address = $order->getShippingAddress()->getData();

        $orderItems = $order->getItemsCollection();
        $items = array();

        foreach ($orderItems as $item){
            $items[] = array(
                'productId' => $item->product_id,
                'initialPrice' => $item->getPrice(),
                'taxPrice' => $item->getPriceInclTax(),
                'productName' => $item->getName(),
                'quantity' => (int) $item->getData('qty_ordered')
            );
        }

        $preparedOrder = array(
            'site' => $order->getStore()->getCode(),
            'externalId' => $order->getId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'customerId' => ($order->getCustomerIsGuest() == 0) ? $order->getCustomerId() : 'g' . $order->getRealOrderId(),
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $address['telephone'],
            'paymentType' => $payments[$order->getPayment()->getMethodInstance()->getCode()],
            'paymentStatus' => $paymentsStatuses[$order->getStatus()],
            'status' => $statuses[$order->getStatus()],
            'discount' => abs($order->getDiscountAmount()),
            'items' => $items,
            'delivery' => array(
                'code' => $shippings[$order->getShippingMethod()],
                'cost' => $order->getShippingAmount(),
                'address' => array(
                    'index' => $address['postcode'],
                    'city' => $address['city'],
                    'country' => $address['country_id'],
                    'street' => $address['street'],
                    'region' => $address['region'],

                ),
            )
        );

        try {
            $this->_api->ordersEdit($preparedOrder);
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log($e->getMessage());
        }
    }*/

    /*public function processOrders($orders, $nocheck = false)
    {

        if (!$nocheck) {
            foreach ($orders as $idx => $order) {
                $customer = array();
                $customer['phones'][]['number'] = $order['phone'];
                $customer['externalId'] = $order['customerId'];
                $customer['firstName'] = $order['firstName'];
                $customer['lastName'] = $order['lastName'];
                $customer['patronymic'] = $order['patronymic'];
                $customer['address'] = $order['delivery']['address'];

                if (isset($order['email'])) {
                    $customer['email'] = $order['email'];
                }

                $checkResult = $this->checkCustomers($customer);

                if ($checkResult === false) {
                    unset($orders[$idx]["customerId"]);
                } else {
                    $orders[$idx]["customerId"] = $checkResult;
                }
            }
        }

        $splitOrders = array_chunk($orders, 50);

        foreach($splitOrders as $orders) {
            try {
                $response = $this->_api->ordersUpload($orders);
                time_nanosleep(0, 250000000);
                if (!$response->isSuccessful()) {
                    Mage::log('RestApi::ordersUpload::API: ' . $response->getErrorMsg());
                    if (isset($response['errors'])) {
                        foreach ($response['errors'] as $error) {
                            Mage::log('RestApi::ordersUpload::API: ' . $error);
                        }
                    }
                }
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log('RestApi::ordersUpload::Curl: ' . $e->getMessage());
                return false;
            }
        }
    }*/

    /*public function orderHistory()
    {
        try {
            $orders = $this->_api->ordersHistory(new DateTime($this->getDate($this->historyLog)));
            Mage::log($orders->getGeneratedAt(), null, 'history.log');
            return $orders['orders'];
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log('RestApi::orderHistory::Curl: ' . $e->getMessage());
            return false;
        }
    }*/

    /**
     * @param $data
     * @return bool
     */
    /*public function orderFixExternalIds($data)
    {
        try {
            $this->_api->ordersFixExternalIds($data);
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log('RestApi::orderFixExternalIds::Curl: ' . $e->getMessage());
            return false;
        }

        return true;
    }*/

    /**
     * @param $data
     * @return bool
     */
    /*public function customerFixExternalIds($data)
    {
        try {
            $this->_api->customersFixExternalIds($data);
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log('RestApi::customerFixExternalIds::Curl: ' . $e->getMessage());
            return false;
        }

        return true;
    }*/

    /**
     * Export References to CRM
     *
     * @param array $deliveries deliveries data
     * @param array $payments   payments data
     * @param array $statuses   statuses data
     *
     */
    /*public function processReference($deliveries = null, $payments = null, $statuses = null)
    {
        if ($deliveries != null) {
            $this->processDeliveries($deliveries);
        }

        if ($payments != null) {
            $this->processPayments($payments);
        }

        if ($statuses != null) {
            $this->processStatuses($statuses);
        }
    }*/

    /**
     * Export deliveries
     *
     * @param array $deliveries
     *
     * @return bool
     */
    /*protected function processDeliveries($deliveries)
    {
        foreach ($deliveries as $delivery) {
            try {
                $this->_api->deliveryTypesEdit($delivery);
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log('RestApi::deliveryEdit::Curl: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }*/

    /**
     * Export payments
     *
     * @param array $payments
     *
     * @return bool
     */
    /*protected function processPayments($payments)
    {
        foreach ($payments as $payment) {
            try {
                $this->_api->paymentTypesEdit($payment);
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log('RestApi::paymentEdit::Curl: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }*/

    /**
     * Export statuses
     *
     * @param array $statuses
     *
     * @return bool
     */
    /*protected function processStatuses($statuses)
    {
        foreach ($statuses as $status) {
            try {
                $this->_api->statusesEdit($status);
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log('RestApi::statusEdit::Curl: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }*/
}
