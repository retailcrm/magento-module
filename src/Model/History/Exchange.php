<?php

namespace Retailcrm\Retailcrm\Model\History;

class Exchange
{
    private $api;
    private $config;
    private $helper;
    private $logger;
    private $resourceConfig;
    private $customerFactory;
    private $quote;
    private $customerRepository;
    private $product;
    private $shipconfig;
    private $quoteManagement;
    private $registry;
    private $cacheTypeList;
    private $order;
    private $orderManagement;
    private $eventManager;
    private $objectManager;
    private $orderInterface;
    private $storeManager;
    private $regionFactory;

    public function __construct(
        \Magento\Framework\App\ObjectManager $objectManager,
        \Retailcrm\Retailcrm\Helper\Data $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Quote\Model\QuoteFactory $quote,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Catalog\Model\Product $product,
        \Magento\Shipping\Model\Config $shipconfig,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\Event\Manager $eventManager,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Retailcrm\Retailcrm\Helper\Proxy $api
    ) {
        $this->shipconfig = $shipconfig;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->config = $config;
        $this->resourceConfig = $resourceConfig;
        $this->customerFactory = $customerFactory;
        $this->quote = $quote;
        $this->customerRepository = $customerRepository;
        $this->product = $product;
        $this->quoteManagement = $quoteManagement;
        $this->registry = $registry;
        $this->cacheTypeList = $cacheTypeList;
        $this->orderInterface = $orderInterface;
        $this->orderManagement = $orderManagement;
        $this->eventManager = $eventManager;
        $this->objectManager = $objectManager;
        $this->order = $order;
        $this->storeManager = $storeManager;
        $this->regionFactory = $regionFactory;
        $this->api = $api;
    }

    /**
     * Get orders history from CRM
     *
     * @return boolean
     */
    public function ordersHistory()
    {
        if (!$this->api->isConfigured()) {
            return false;
        }

        $this->registry->register('RETAILCRM_HISTORY', true);

        $historyFilter = [];
        $historyOrder = [];

        $historyStart = $this->config->getValue('retailcrm/general/filter_history');

        if ($historyStart && $historyStart > 0) {
            $historyFilter['sinceId'] = $historyStart;
        }

        while (true) {
            $response = $this->api->ordersHistory($historyFilter);

            if ($response === false) {
                return false;
            }

            if (!$response->isSuccessful()) {
                return true;
            }

            $orderH = isset($response['history']) ? $response['history'] : [];

            if (empty($orderH)) {
                return true;
            }

            $historyOrder = array_merge($historyOrder, $orderH);
            $end = array_pop($orderH);
            $historyFilter['sinceId'] = $end['id'];

            if ($response['pagination']['totalPageCount'] == 1) {
                $this->resourceConfig->saveConfig(
                    'retailcrm/general/filter_history',
                    $historyFilter['sinceId'],
                    'default',
                    0
                );
                $this->cacheTypeList->cleanType('config');

                $orders = self::assemblyOrder($historyOrder);

                $this->logger->writeDump($orders, 'OrderHistory');

                $this->processOrders($orders);

                return true;
            }
        }

        $this->registry->register('RETAILCRM_HISTORY', false);
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
        $this->logger->writeDump($orders, 'processOrders');

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
        $this->logger->writeDump($order, 'doCreate');

        $payments = $this->config->getValue('retailcrm/retailcrm_payment');
        $shippings = $this->config->getValue('retailcrm/retailcrm_shipping');
        $sites = $this->helper->getMappingSites();

        if ($sites) {
            $store = $this->storeManager->getStore($sites[$order['site']]);
            $websiteId = $store->getWebsiteId();
        } else {
            $store = $this->storeManager->getStore();
            $websiteId = $this->storeManager->getStore()->getWebsiteId();
        }

        $region = $this->regionFactory->create();
        $customer = $this->customerFactory->create();
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
                $this->logger->writeRow($exception->getMessage());
            }

            $this->api->customersFixExternalIds(
                [
                    [
                        'id' => $order['customer']['id'],
                        'externalId' => $customer->getId()
                    ]
                ]
            );
        }

        //Create object of quote
        $quote = $this->quote->create();

        //set store for which you create quote
        $quote->setStore($store);

        // if you have allready buyer id then you can load customer directly
        $customer = $this->customerRepository->getById($customer->getId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer

        //add items in quote
        foreach ($order['items'] as $item) {
            $product = $this->product->load($item['offer']['externalId']);
            $product->setPrice($item['initialPrice']);
            $quote->addProduct(
                $product,
                (int)$item['quantity']
            );
        }

        $products = [];

        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = ['qty' => $item['quantity']];
        }

        $orderData = [
            'currency_id' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
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

        if ($this->api->getVersion() == 'v4') {
            $paymentType = $order['paymentType'];
        } elseif ($this->api->getVersion() == 'v5') {
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
        $magentoOrder = $this->quoteManagement->submit($quote);

        $increment_id = $magentoOrder->getId();

        $this->api->ordersFixExternalIds(
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
        $this->logger->writeDump($order, 'doCreateUp');

        $response = $this->api->ordersGet($order['id'], $by = 'id');

        if (!$response->isSuccessful()) {
            return;
        }

        if (isset($response['order'])) {
            $order = $response['order'];
        }

        $payments = $this->config->getValue('retailcrm/retailcrm_payment');
        $shippings = $this->config->getValue('retailcrm/retailcrm_shipping');

        $region = $this->regionFactory->create();
        $sites = $this->helper->getMappingSites();

        if ($sites) {
            $store = $this->storeManager->getStore($sites[$order['site']]);
            $websiteId = $store->getWebsiteId();
        } else {
            $store = $this->storeManager->getStore();
            $websiteId = $this->storeManager->getStore()->getWebsiteId();
        }

        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);

        if (isset($order['customer']['externalId'])) {
            $customer->load($order['customer']['externalId']); // load customer by external id
        }

        //Create object of quote
        $quote = $this->quote->create();

        //set store for which you create quote
        $quote->setStore($store);
        $quote->setCurrency();

        // if you have all ready buyer id then you can load customer directly
        if ($customer->getId()) {
            $customer = $this->customerRepository->getById($customer->getId());
            $quote->assignCustomer($customer); //Assign quote to customer
        } else {
            $quote->setCustomerEmail($order['email']);
            $quote->setCustomerIsGuest(1);
        }

        //add items in quote
        foreach ($order['items'] as $item) {
            $product = $this->product->load($item['offer']['externalId']);
            $product->setPrice($item['initialPrice']);
            $quote->addProduct(
                $product,
                (int)$item['quantity']
            );
        }

        $products = [];

        foreach ($order['items'] as $item) {
            $products[$item['offer']['externalId']] = ['qty' => $item['quantity']];
        }

        $orderData = [
            'currency_id' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'email' => $order['email'],
            'shipping_address' => [
                'firstname' => $order['firstName'],
                'lastname' => $order['lastName'],
                'street' => $order['delivery']['address']['street'],
                'city' => $order['delivery']['address']['city'],
                'country_id' => $order['countryIso'],//US
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

        if ($this->api->getVersion() == 'v4') {
            $paymentType = $order['paymentType'];
        } elseif ($this->api->getVersion() == 'v5') {
            $paymentType = $this->getPaymentMethod($order['payments'], false);
        }

        $quote->setPaymentMethod($payments[$paymentType]);
        $quote->setInventoryProcessed(false);

        $originalId = $order['externalId'];
        $oldOrder = $this->orderInterface->load($originalId);

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
        $magentoOrder = $this->quoteManagement->submit($quote, $orderDataUp);
        $oldOrder->setStatus('canceled')->save();
        $increment_id = $magentoOrder->getId();

        $this->api->ordersFixExternalIds(
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
        $this->logger->writeDump($order, 'doUpdate');

        $Status = $this->config->getValue('retailcrm/retailcrm_status');
        $Status = array_flip(array_filter($Status));

        $magentoOrder = $this->order->load($order['externalId']);
        $magentoOrderArr = $magentoOrder->getData();

        $this->logger->writeDump($magentoOrderArr, 'magentoOrderArr');
        $this->logger->writeDump($Status, 'status');

        if ((!empty($order['order_edit'])) && ($order['order_edit'] == 1)) {
            $this->doCreateUp($order);
        }

        if (!empty($order['status'])) {
            $change = $Status[$order['status']];

            if ($change == 'canceled') {
                $this->orderManagement->cancel($magentoOrderArr['entity_id']);
            }

            $order_status = $this->order->load($magentoOrder->getId());
            $order_status->setStatus($change);
            $order_status->save();
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
            $orderId = $change['order']['id'];
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
                    $orders[$change['order']['id']]['items'][$change['item']['id']] = array_merge(
                        $orders[$change['order']['id']]['items'][$change['item']['id']],
                        $change['item']
                    );
                } else {
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
            } else {
                if (isset($fields['delivery'][$change['field']])
                    && $fields['delivery'][$change['field']] == 'service'
                ) {
                    $orders[$orderId]['delivery']['service']['code'] = self::newValue($change['newValue']);
                } elseif (isset($fields['delivery'][$change['field']])) {
                    $field = $fields['delivery'][$change['field']];
                    $orders[$orderId]['delivery'][$field] = self::newValue($change['newValue']);
                    unset($field);
                } elseif (isset($fields['orderAddress'][$change['field']])) {
                    $field = $fields['orderAddress'][$change['field']];
                    $orders[$orderId]['delivery']['address'][$field] = self::newValue($change['newValue']);
                    unset($field);
                } elseif (isset($fields['integrationDelivery'][$change['field']])) {
                    $field = $fields['integrationDelivery'][$change['field']];
                    $orders[$orderId]['delivery']['service'][$field] = self::newValue($change['newValue']);
                    unset($field);
                } elseif (isset($fields['customerContragent'][$change['field']])) {
                    $field = $fields['customerContragent'][$change['field']];
                    $orders[$orderId][$field] = self::newValue($change['newValue']);
                    unset($field);
                } elseif (strripos($change['field'], 'custom_') !== false) {
                    $field = str_replace('custom_', '', $change['field']);
                    $orders[$orderId]['customFields'][$field] = self::newValue($change['newValue']);
                    unset($field);
                } elseif (isset($fields['order'][$change['field']])) {
                    $orders[$orderId][$fields['order'][$change['field']]] = self::newValue($change['newValue']);
                }

                if (isset($change['created'])) {
                    $orders[$orderId]['create'] = 1;
                }

                if (isset($change['deleted'])) {
                    $orders[$orderId]['deleted'] = 1;
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
                if (!empty($element) || $element === 0 || $element === '0') {
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
        if (isset($value['code'])) {
            return $value['code'];
        } else {
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
        $activeCarriers = $this->shipconfig->getActiveCarriers();

        foreach ($activeCarriers as $carrierCode => $carrierModel) {
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
    private function getPaymentMethod($payments, $newOrder = true)
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
