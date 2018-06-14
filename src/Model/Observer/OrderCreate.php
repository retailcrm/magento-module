<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;
use RetailCrm\Retailcrm\Helper\Data as Helper;

class OrderCreate implements \Magento\Framework\Event\ObserverInterface
{
    protected $api;
    protected $config;
    protected $logger;
    protected $helper;

    private $registry;
    private $product;
    private $order;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\Registry $registry
     * @param \Retailcrm\Retailcrm\Model\Logger\Logger $logger
     * @param \Magento\Catalog\Model\ProductRepository $product
     * @param Helper $helper
     * @param ApiClient $api
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Magento\Catalog\Model\ProductRepository $product,
        Helper $helper,
        ApiClient $api
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->registry = $registry;
        $this->product = $product;
        $this->helper = $helper;
        $this->api = $api;
        $this->order = [];
    }

    /**
     * Execute send order in CRM
     *
     * @param Observer $observer
     *
     * @return mixed
     */
    public function execute(Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();

        $this->api->setSite($this->helper->getSite($order->getStore()));

        if ($this->existsInCrm($order->getId()) === true) {
            return false;
        }

        $addressObj = $order->getBillingAddress();

        $shippingCode = $this->getShippingCode($order->getShippingMethod());

        $this->order = [
            'externalId' => $order->getId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'lastName' => $order->getCustomerLastname()
                ? $order->getCustomerLastname()
                : $addressObj->getLastname(),
            'firstName' => $order->getCustomerFirstname()
                ? $order->getCustomerFirstname()
                : $addressObj->getFirstname(),
            'patronymic' => $order->getCustomerMiddlename()
                ? $order->getCustomerMiddlename()
                : $addressObj->getMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $addressObj->getTelephone(),
            'status' => $this->config->getValue('retailcrm/retailcrm_status/' . $order->getStatus()),
            'items' => $this->getOrderItems($order),
            'delivery' => [
                'code' => $this->config->getValue('retailcrm/retailcrm_shipping/' . $shippingCode),
                'cost' => $order->getShippingAmount(),
                'address' => [
                    'index' => $addressObj->getData('postcode'),
                    'city' => $addressObj->getData('city'),
                    'street' => $addressObj->getData('street'),
                    'region' => $addressObj->getData('region'),
                    'text' => trim(
                        ',',
                        implode(
                            ',',
                            [
                                $addressObj->getData('postcode'),
                                $addressObj->getData('city'),
                                $addressObj->getData('street'),
                            ]
                        )
                    )
                ]
            ]
        ];

        if ($addressObj->getData('country_id')) {
            $this->order['countryIso'] = $addressObj->getData('country_id');
        }

        if ($this->api->getVersion() == 'v4') {
            $this->order['paymentType'] = $this->config->getValue(
                'retailcrm/retailcrm_payment/' . $order->getPayment()->getMethodInstance()->getCode()
            );
            $this->order['discount'] = abs($order->getDiscountAmount());
        } elseif ($this->api->getVersion() == 'v5') {
            $this->order['discountManualAmount'] = abs($order->getDiscountAmount());

            $payment = [
                'type' => $this->config->getValue(
                    'retailcrm/retailcrm_payment/' . $order->getPayment()->getMethodInstance()->getCode()
                ),
                'externalId' => $order->getId(),
                'order' => [
                    'externalId' => $order->getId(),
                ]
            ];

            if ($order->getBaseTotalDue() == 0) {
                $payment['status'] = 'paid';
            }

            $this->order['payments'][] = $payment;
        }

        if (trim($this->order['delivery']['code']) == '') {
            unset($this->order['delivery']['code']);
        }

        if (isset($this->order['paymentType']) && trim($this->order['paymentType']) == '') {
            unset($this->order['paymentType']);
        }

        if (trim($this->order['status']) == '') {
            unset($this->order['status']);
        }

        $this->setCustomer(
            $order,
            $addressObj
        );

        Helper::filterRecursive($this->order);

        $this->logger->writeDump($this->order, 'CreateOrder');

        $this->api->ordersCreate($this->order);

        return $this;
    }

    /**
     * @param $order
     * @param $addressObj
     */
    private function setCustomer($order, $addressObj)
    {
        if ($order->getCustomerIsGuest() == 1) {
            $customer = $this->getCustomerByEmail($order->getCustomerEmail());

            if ($customer !== false) {
                $this->order['customer']['id'] = $customer['id'];
            }
        }

        if ($order->getCustomerIsGuest() == 0) {
            if ($this->existsInCrm($order->getCustomerId(), 'customersGet')) {
                $this->order['customer']['externalId'] = $order->getCustomerId();
            } else {
                $preparedCustomer = [
                    'externalId' => $order->getCustomerId(),
                    'firstName' => $order->getCustomerFirstname(),
                    'lastName' => $order->getCustomerLastname(),
                    'email' => $order->getCustomerEmail()
                ];

                if ($addressObj->getTelephone()) {
                    $preparedCustomer['phones'][] = [
                        'number' => $addressObj->getTelephone()
                    ];
                }

                if ($this->api->customersCreate($preparedCustomer)) {
                    $this->order['customer']['externalId'] = $order->getCustomerId();
                }
            }
        }
    }

    /**
     * Get order products
     *
     * @param object $order
     *
     * @return array $items
     */
    private function getOrderItems($order)
    {
        $items = [];

        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() == "simple") {
                $price = $item->getPrice();

                if ($price == 0) {
                    $magentoProduct = $this->product->getById($item->getProductId());
                    $price = $magentoProduct->getPrice();
                }

                $product = [
                    'productId' => $item->getProductId(),
                    'productName' => $item->getName(),
                    'quantity' => $item->getQtyOrdered(),
                    'initialPrice' => $price,
                    'offer' => [
                        'externalId' => $item->getProductId()
                    ]
                ];

                unset($magentoProduct);
                unset($price);

                $items[] = $product;
            }
        }

        return $items;
    }

    /**
     * Get shipping code
     *
     * @param string $string
     *
     * @return string
     */
    public function getShippingCode($string)
    {
        $split = array_values(explode('_', $string));
        $length = count($split);
        $prepare = array_slice($split, 0, $length/2);

        return implode('_', $prepare);
    }

    /**
     * Check exists order or customer in CRM
     *
     * @param int $id
     * @param string $method
     * @param string $by
     * @param string $site
     *
     * @return boolean
     */
    private function existsInCrm($id, $method = 'ordersGet', $by = 'externalId', $site = null)
    {
        $response = $this->api->{$method}($id, $by, $site);

        if ($response === false) {
            return false;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            return false;
        }

        return true;
    }

    /**
     * Get customer by email from CRM
     *
     * @param string $email
     *
     * @return mixed
     */
    private function getCustomerByEmail($email)
    {
        $response = $this->api->customersList(['email' => $email]);

        if ($response === false) {
            return false;
        }

        if ($response->isSuccessful() && isset($response['customers'])) {
            if (!empty($response['customers'])) {
                return reset($response['customers']);
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getOrder()
    {
        return $this->order;
    }
}
