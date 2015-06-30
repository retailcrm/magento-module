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
            //'paymentStatus' => $paymentsStatuses[$order->getStatus()],
            //'status' => $statuses[$order->getStatus()],
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
            /*Mage::getModel('core/config')->saveConfig(
                'retailcrm/general/history', $time
            );*/

            foreach ($orders as $order) {
                if(!empty($order['externalId'])) {
                    $this->doUpdate($order);
                } else {
                    $this->doCreate($order);
                }
            }
        }
    }

    /**
     * @param array $order
     */
    private function doCreate($order)
    {
        $this->_config = Mage::getStoreConfig('retailcrm');
        $statuses = array_flip(array_filter($this->_config['status']));
        $paymentsStatuses = array_filter($this->_config['paymentstatus']);
        $payments = array_flip(array_filter($this->_config['payment']));
        $shippings = array_flip(array_filter($this->_config['shipping']));

        // create new order & fix externalId

        Mage::log("Create: ", null, 'history.log');
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
                        $magentoOrder->cancel();
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
}
