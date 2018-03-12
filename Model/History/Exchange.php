<?php

namespace Retailcrm\Retailcrm\Model\History;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class Exchange
{
    protected $_api;
    protected $_config;
    protected $_helper;
    protected $_logger;
    protected $_resourceConfig;
    protected $_customerFactory;
    protected $_quote;
    protected $_customerRepository;
    protected $_product;
    protected $_shipconfig;
    protected $_quoteManagement;
    protected $_registry;
    protected $_cacheTypeList;
    protected $_order;
    protected $_orderManagement;
    //protected $_transaction; 
    //protected $_invoiceService;
    protected $_eventManager;
    protected $_objectManager;

    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->get('\Retailcrm\Retailcrm\Helper\Data');
        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $resourceConfig = $objectManager->get('Magento\Config\Model\ResourceModel\Config');
        $customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory');
        $quote = $objectManager->get('\Magento\Quote\Model\QuoteFactory');
        $customerRepository = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface');
        $product = $objectManager->get('\Magento\Catalog\Model\Product');
        $shipconfig = $objectManager->get('\Magento\Shipping\Model\Config');
        $quoteManagement = $objectManager->get('\Magento\Quote\Model\QuoteManagement');
        $registry = $objectManager->get('\Magento\Framework\Registry');
        $cacheTypeList = $objectManager->get('\Magento\Framework\App\Cache\TypeListInterface');
        $order = $objectManager->get('\Magento\Sales\Api\Data\OrderInterface');
        $orderManagement = $objectManager->get('\Magento\Sales\Api\OrderManagementInterface');		
        //$invoiceService = $objectManager->get('\Magento\Sales\Model\Service\InvoiceService');
        //$transaction = $objectManager->get('\Magento\Framework\DB\Transaction');
        $eventManager = $objectManager->get('\Magento\Framework\Event\Manager');
        $logger = new \Retailcrm\Retailcrm\Model\Logger\Logger($objectManager);

        $this->_shipconfig = $shipconfig;
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_config = $config;
        $this->_resourceConfig = $resourceConfig;
        $this->_customerFactory = $customerFactory;
        $this->_quote = $quote;
        $this->_customerRepository = $customerRepository;
        $this->_product = $product;
        $this->_quoteManagement = $quoteManagement;
        $this->_registry = $registry;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_order = $order;
        $this->_orderManagement = $orderManagement;
        //$this->_transaction = $transaction;
        //$this->_invoiceService = $invoiceService;
        $this->_eventManager = $eventManager;
        $this->_objectManager = $objectManager;

        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        $version = $config->getValue('retailcrm/general/api_version');

        if (!empty($url) && !empty($key)) {
            $this->_api = new ApiClient($url, $key, $version);
        }
    }

    /**
     * Get orders history from CRM
     * 
     * @return boolean
     */
    public function ordersHistory()
    {
        $this->_registry->register('RETAILCRM_HISTORY', true);

        $historyFilter = [];
        $historyOrder = [];

        $historyStart = $this->_config->getValue('retailcrm/general/filter_history');

        if ($historyStart && $historyStart > 0) {
            $historyFilter['sinceId'] = $historyStart;
        }

        while (true) {
            $response = $this->_api->ordersHistory($historyFilter);

            if ($response === false) {
                return;
            }

            if (!$response->isSuccessful()) {
                return true;
            }

            $orderH = isset($response['history']) ? $response['history'] : [];

            if (count($orderH) == 0) {
                return true;
            }

            $historyOrder = array_merge($historyOrder, $orderH);
            $end = array_pop($orderH);
            $historyFilter['sinceId'] = $end['id'];

            if ($response['pagination']['totalPageCount'] == 1) {
                $this->_resourceConfig->saveConfig('retailcrm/general/filter_history', $historyFilter['sinceId'], 'default', 0);
                $this->_cacheTypeList->cleanType('config');

                $orders = self::assemblyOrder($historyOrder);

                $this->_logger->writeDump($orders,'OrderHistory');

                $this->processOrders($orders);

                return true;
            }
        }//endwhile

        $this->_registry->register('RETAILCRM_HISTORY', false);
    }

    /**
     * Process orders
     * 
     * @param array $orders
     * 
     * @return void
     */
    private function processOrders($orders)
    {
        $this->_logger->writeDump($orders,'processOrders');

        if (!empty($orders)) {
            foreach ($orders as $order) {
                if (isset($order['externalId']) && !empty($order['externalId'])) {
                    $this->doUpdate($order);
                } else {
                    $this->doCreate($order);
                }
            }
        }
    }

    /**
     * Create new order from CRM
     * 
     * @param array $order
     * 
     * @return void
     */
    private function doCreate($order)
    {
        $this->_logger->writeDump($order,'doCreate');

        $payments = $this->_config->getValue('retailcrm/Payment');
        $shippings = $this->_config->getValue('retailcrm/Shipping');

        $manager = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $region = $this->_objectManager->get('Magento\Directory\Model\RegionFactory')->create();
        $store = $manager->getStore();
        $websiteId = $manager->getStore()->getWebsiteId();

        $customer = $this->_customerFactory->create();
        $customer->setWebsiteId($websiteId);

        if (isset($order['customer']['externalId'])) {
            $customer->load($order['customer']['externalId']);
        }

        if (!$customer->getId()) {
            //If not avilable then create this customer
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($order['firstName'])
                ->setLastname($order['lastName'])
                ->setEmail($order['email'])
                ->setPassword($order['email']);
            try {
                $customer->save();
            } catch (\Exception $exception) {
                $this->_logger->writeRow($exception->getMessage());
            }

            $this->_api->customersFixExternalIds(
                [
                    [
                        'id' => $order['customer']['id'],
                        'externalId' => $customer->getId()
                    ]
                ]
            );
        }

        //Create object of quote
        $quote = $this->_quote->create();

        //set store for which you create quote
        $quote->setStore($store); 

        // if you have allready buyer id then you can load customer directly
        $customer = $this->_customerRepository->getById($customer->getId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer

        //add items in quote
        foreach($order['items'] as $item){
            $product = $this->_product->load($item['offer']['externalId']);
            $product->setPrice($item['initialPrice']);
            $quote->addProduct(
                $product,
                intval($item['quantity'])
            );
        }

        $products = [];

        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = ['qty' => $item['quantity']];
        }

        $orderData = [
            'currency_id' => $manager->getStore()->getCurrentCurrency()->getCode(),
            'email' => $order['email'],
            'shipping_address' => [
                'firstname' => $order['firstName'],
                'lastname' => $order['lastName'],
                'street' => $order['delivery']['address']['street'],
                'city' => $order['delivery']['address']['city'],
                'country_id' => $order['countryIso'],
                'region' => $order['delivery']['address']['region'],
                'postcode' => $order['delivery']['address']['index'],
                'telephone' => $order['phone'],
                'save_in_address_book' => 1
            ],
            'items'=> $products
        ];

        $region->loadByName($order['delivery']['address']['region'], $order['countryIso']);

        if ($region->getId()) {
            $orderData['shipping_address']['region_id'] = $region->getId();
        }

        $shippings = array_flip(array_filter($shippings));
        $payments = array_flip(array_filter($payments));

        $ShippingMethods = $this->getAllShippingMethodsCode($shippings[$order['delivery']['code']]);

        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($ShippingMethods);

        if ($this->_api->getVersion() == 'v4') {
            $paymentType = $order['paymentType'];
        } elseif ($this->_api->getVersion() == 'v5') {
            if ($order['payments']) {
                $paymentType = $this->getPaymentMethod($order['payments']);
            }
        }

        $quote->setPaymentMethod($payments[$paymentType]);
        $quote->setInventoryProcessed(false);

        $quote->save();

        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => $payments[$paymentType]]);

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        // Create Order From Quote
        $magentoOrder = $this->_quoteManagement->submit($quote);

        $increment_id = $magentoOrder->getId();

        $this->_api->ordersFixExternalIds(
            [
                [
                    'id' => $order['id'],
                    'externalId' => $increment_id
                ]
            ]
        );
    }

    /**
     * Create old edited order
     * 
     * @param array $order
     * 
     * @return void
     */
    private function doCreateUp($order)
    {
        $this->_logger->writeDump($order,'doCreateUp');

        $response = $this->_api->ordersGet($order['id'], $by = 'id');

        if (!$response->isSuccessful()) {
            return;
        }

        if (isset($response['order'])) {
            $order = $response['order'];
        }

        $payments = $this->_config->getValue('retailcrm/Payment');
        $shippings = $this->_config->getValue('retailcrm/Shipping');

        $manager = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $region = $this->_objectManager->get('Magento\Directory\Model\RegionFactory')->create();
        $store = $manager->getStore();
        $websiteId = $manager->getStore()->getWebsiteId();

        $customer = $this->_customerFactory->create();
        $customer->setWebsiteId($websiteId);

        if (isset($order['customer']['externalId'])) {
            $customer->load($order['customer']['externalId']); // load customet by external id
        }

        //Create object of quote
        $quote = $this->_quote->create();

        //set store for which you create quote
        $quote->setStore($store);
        $quote->setCurrency();

        // if you have allready buyer id then you can load customer directly
        if ($customer->getId()) {
            $customer = $this->_customerRepository->getById($customer->getId());
            $quote->assignCustomer($customer); //Assign quote to customer
        } else {
            $quote->setCustomerEmail($order['email']);
            $quote->setCustomerIsGuest(1);
        }

        //add items in quote
        foreach($order['items'] as $item){
            $product = $this->_product->load($item['offer']['externalId']);
            $product->setPrice($item['initialPrice']);
            $quote->addProduct(
                $product,
                intval($item['quantity'])
            );
        }

        $products = [];

        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = ['qty' => $item['quantity']];
        }

        $orderData = [
            'currency_id' => $manager->getStore()->getCurrentCurrency()->getCode(),
            'email' => $order['email'],
            'shipping_address' =>array(
                'firstname' => $order['firstName'],
                'lastname' => $order['lastName'],
                'street' => $order['delivery']['address']['street'],
                'city' => $order['delivery']['address']['city'],
                'country_id' => $order['countryIso'],//US
                'region' => $order['delivery']['address']['region'],
                'postcode' => $order['delivery']['address']['index'],
                'telephone' => $order['phone'],
                'save_in_address_book' => 1
            ),
            'items'=> $products
        ];

        $region->loadByName($order['delivery']['address']['region'], $order['countryIso']);

        if ($region->getId()) {
            $orderData['shipping_address']['region_id'] = $region->getId();
        }

        $shippings = array_flip(array_filter($shippings));
        $payments = array_flip(array_filter($payments));

        $ShippingMethods = $this->getAllShippingMethodsCode($shippings[$order['delivery']['code']]);

        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($ShippingMethods);

        if ($this->_api->getVersion() == 'v4') {
            $paymentType = $order['paymentType'];
        } elseif ($this->_api->getVersion() == 'v5') {
            $paymentType = $this->getPaymentMethod($order['payments'], false);
        }

        $quote->setPaymentMethod($payments[$paymentType]);
        $quote->setInventoryProcessed(false);


        $originalId = $order['externalId'];
        $oldOrder = $this->_order->load($originalId);

        $orderDataUp = [
            'original_increment_id'     => $oldOrder->getIncrementId(),
            'relation_parent_id'        => $oldOrder->getId(),
            'relation_parent_real_id'   => $oldOrder->getIncrementId(),
            'edit_increment'            => $oldOrder->getEditIncrement() + 1,
            'increment_id'              => $oldOrder->getIncrementId() . '-' . ($oldOrder->getEditIncrement() + 1)
        ];

        $quote->setReservedOrderId($orderDataUp['increment_id']);
        $quote->save();

        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => $payments[$paymentType]]);

        // Collect Totals & Save Quote
        $quote->collectTotals()->save();

        // Create Order From Quote
        $magentoOrder = $this->_quoteManagement->submit($quote,$orderDataUp);
        $oldOrder->setStatus('canceled')->save();
        $increment_id = $magentoOrder->getId();

        $this->_api->ordersFixExternalIds(
            [
                [
                    'id' => $order['id'],
                    'externalId' => $increment_id
                ]
            ]
        );
    }

    /**
     * Edit order
     * 
     * @param array $order
     * 
     * @return void
     */
    private function doUpdate($order)
    {
        $this->_logger->writeDump($order,'doUpdate');

        $Status = $this->_config->getValue('retailcrm/Status');
        $Status = array_flip(array_filter($Status));

        $magentoOrder = $this->_order->load($order['externalId']);
        $magentoOrderArr = $magentoOrder->getData();

        $this->_logger->writeDump($magentoOrderArr, 'magentoOrderArr');
        $this->_logger->writeDump($Status, 'status');

        if ((!empty($order['order_edit'])) && ($order['order_edit'] == 1)) {
            $this->doCreateUp($order);
        }

        if (!empty($order['status'])) {
            $change = $Status[$order['status']];

            if($change == 'canceled'){
                $this->_orderManagement->cancel($magentoOrderArr['entity_id']);
            }

            if($change == 'holded'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('holded');
                $order_status->save();
            }

            if(($change == 'complete')||($order['status']== 'complete')){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('complete');
                $order_status->save();
            }

            if($change == 'closed'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('closed');
                $order_status->save();
            }

            if($change == 'processing'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('processing');
                $order_status->save();

            }

            if($change == 'fraud'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('fraud');
                $order_status->save();
            }

            if($change == 'payment_review'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('payment_review');
                $order_status->save();
            }

            if($change == 'paypal_canceled_reversal'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('paypal_canceled_reversal');
                $order_status->save();
            }

            if($change == 'paypal_reversed'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('paypal_reversed');
                $order_status->save();
            }

            if($change == 'pending_payment'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('pending_payment');
                $order_status->save();
            }

            if($change == 'pending_paypal'){
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order_status = $objectManager->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
                $order_status->setStatus('pending_paypal');
                $order_status->save();
            }
        }
    }

    /**
     * Assembly orders from history
     * 
     * @param array $orderHistory
     * 
     * @return array $orders
     */
    public static function assemblyOrder($orderHistory)
    {
        $orders = [];

        foreach ($orderHistory as $change) {
            $change['order'] = self::removeEmpty($change['order']);

            if (isset($change['order']['items'])) {
                $items = [];

                foreach ($change['order']['items'] as $item) {
                    if (isset($change['created'])) {
                        $item['create'] = 1;
                    }

                    $items[$item['id']] = $item;
                }

                $change['order']['items'] = $items;
            }

            if (isset($change['order']['contragent']['contragentType'])) {
                $change['order']['contragentType'] = self::newValue($change['order']['contragent']['contragentType']);
                unset($change['order']['contragent']);
            }

            if (isset($orders[$change['order']['id']])) {
                $orders[$change['order']['id']] = array_merge($orders[$change['order']['id']], $change['order']);
            } else {
                $orders[$change['order']['id']] = $change['order'];
            }

            if ($change['field'] == 'manager_comment') {
                $orders[$change['order']['id']][$change['field']] = $change['newValue'];
            }

            if (($change['field'] != 'status')
                && ($change['field'] != 'country')
                && ($change['field'] != 'manager_comment')
                && ($change['field'] != 'order_product.status')
                && ($change['field'] != 'payment_status')
                && ($change['field'] != 'prepay_sum')
            ) {
                $orders[$change['order']['id']]['order_edit'] = 1;
            }

            if (isset($change['item'])) {
                if (isset($orders[$change['order']['id']]['items'])
                && $orders[$change['order']['id']]['items'][$change['item']['id']]
                ) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = array_merge($orders[$change['order']['id']]['items'][$change['item']['id']], $change['item']);
                } else{
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = $change['item'];
                }

                if (empty($change['oldValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['create'] = 1;
                    $orders[$change['order']['id']]['order_edit'] = 1;
                    unset($orders[$change['order']['id']]['items'][$change['item']['id']]['delete']);
                }

                if (empty($change['newValue']) && $change['field'] == 'order_product') {
                    $orders[$change['order']['id']]['items'][$change['item']['id']]['delete'] = 1;
                    $orders[$change['order']['id']]['order_edit'] = 1;
                }

                if (!empty($change['newValue']) && $change['field'] == 'order_product.quantity') {
                    $orders[$change['order']['id']]['order_edit'] = 1;
                }

                if (!$orders[$change['order']['id']]['items'][$change['item']['id']]['create'] && $fields['item'][$change['field']]) {
                    $orders[$change['order']['id']]['items'][$change['item']['id']][$fields['item'][$change['field']]] = $change['newValue'];
                }
            } else {
                if ((isset($fields['delivery'][$change['field']]))&&($fields['delivery'][$change['field']] == 'service')) {
                    $orders[$change['order']['id']]['delivery']['service']['code'] = self::newValue($change['newValue']);
                } elseif (isset($fields['delivery'][$change['field']])) {
                    $orders[$change['order']['id']]['delivery'][$fields['delivery'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (isset($fields['orderAddress'][$change['field']])) {
                    $orders[$change['order']['id']]['delivery']['address'][$fields['orderAddress'][$change['field']]] = $change['newValue'];
                } elseif (isset($fields['integrationDelivery'][$change['field']])) {
                    $orders[$change['order']['id']]['delivery']['service'][$fields['integrationDelivery'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (isset($fields['customerContragent'][$change['field']])) {
                    $orders[$change['order']['id']][$fields['customerContragent'][$change['field']]] = self::newValue($change['newValue']);
                } elseif (strripos($change['field'], 'custom_') !== false) {
                    $orders[$change['order']['id']]['customFields'][str_replace('custom_', '', $change['field'])] = self::newValue($change['newValue']);
                } elseif (isset($fields['order'][$change['field']])) {
                    $orders[$change['order']['id']][$fields['order'][$change['field']]] = self::newValue($change['newValue']);
                }

                if (isset($change['created'])) {
                    $orders[$change['order']['id']]['create'] = 1;
                }

                if (isset($change['deleted'])) {
                    $orders[$change['order']['id']]['deleted'] = 1;
                }
            }
        }

        return $orders;
    }

    /**
     * Remove empty elements
     * 
     * @param array $inputArray
     * 
     * @return array $outputArray
     */
    public static function removeEmpty($inputArray)
    {
        $outputArray = [];

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

    /**
     * Set new value
     *
     * @param mixed $value
     *
     * @return string $value
     */
    public static function newValue($value)
    {
        if(isset($value['code'])) {
            return $value['code'];
        } else{
            return $value;
        }
    }

    /**
     * Get shipping methods
     * 
     * @param string $mcode
     * 
     * @return string
     */
    public function getAllShippingMethodsCode($mcode)
    {
        $activeCarriers = $this->_shipconfig->getActiveCarriers();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        foreach($activeCarriers as $carrierCode => $carrierModel) {
            $options = [];

            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_'. $methodCode;

                    if ($mcode == $carrierCode) {
                        $methods[$mcode] = $code;
                    }
                }
            }
        }

        return $methods[$mcode];
    }

    /**
     * Get payment type for api v5
     * 
     * @param array $payments
     * @param boolean $newOrder
     * 
     * @return mixed
     */
    protected function getPaymentMethod($payments, $newOrder = true)
    {
        if (count($payments) == 1 || $newOrder) {
            $payment = reset($payments);
        } elseif (count($payments) > 1 && !$newOrder) {
            foreach ($payments as $paymentCrm) {
                if (isset($paymentCrm['externalId'])) {
                    $payment = $paymentCrm;
                }
            }
        }

        if (isset($payment)) {
            return $payment['type'];
        }

        return false;
    }
}
