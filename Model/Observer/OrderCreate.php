<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderCreate implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $config;
    private $logger;
    private $registry;
    private $product;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Retailcrm\Retailcrm\Model\Logger\Logger $logger
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Magento\Catalog\Model\ProductRepository $product,
        ApiClient $api
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->registry = $registry;
        $this->product = $product;
        $this->api = $api;
    }

    /**
     * Execute send order in CRM
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        
        if ($this->existsInCrm($order->getId()) === true) {
            return;
        }

        $addressObj = $order->getBillingAddress();

        $shippingCode = $this->getShippingCode($order->getShippingMethod());

        $preparedOrder = [
            'site' => $order->getStore()->getCode(),
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
            'status' => $this->config->getValue('retailcrm/Status/' . $order->getStatus()),
            'items' => $this->getOrderItems($order),
            'delivery' => [
                'code' => $this->config->getValue('retailcrm/Shipping/' . $shippingCode),
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
            $preparedOrder['countryIso'] = $addressObj->getData('country_id');
        }

        if ($this->api->getVersion() == 'v4') {
            $preparedOrder['paymentType'] = $this->config->getValue(
                'retailcrm/Payment/' . $order->getPayment()->getMethodInstance()->getCode()
            );
            $preparedOrder['discount'] = abs($order->getDiscountAmount());
        } elseif ($this->api->getVersion() == 'v5') {
            $preparedOrder['discountManualAmount'] = abs($order->getDiscountAmount());

            $payment = [
                'type' => $this->config->getValue(
                    'retailcrm/Payment/' . $order->getPayment()->getMethodInstance()->getCode()
                ),
                'externalId' => $order->getId(),
                'order' => [
                    'externalId' => $order->getId(),
                ]
            ];

            if ($order->getBaseTotalDue() == 0) {
                $payment['status'] = 'paid';
            }

            $preparedOrder['payments'][] = $payment;
        }

        if (trim($preparedOrder['delivery']['code']) == '') {
            unset($preparedOrder['delivery']['code']);
        }

        if (isset($preparedOrder['paymentType']) && trim($preparedOrder['paymentType']) == '') {
            unset($preparedOrder['paymentType']);
        }

        if (trim($preparedOrder['status']) == '') {
            unset($preparedOrder['status']);
        }

        $this->setCustomer(
            $order,
            $addressObj,
            $preparedOrder
        );

        \Retailcrm\Retailcrm\Helper\Data::filterRecursive($preparedOrder);

        $this->logger->writeDump($preparedOrder, 'CreateOrder');

        $this->api->ordersCreate($preparedOrder);

        return $this;
    }

    /**
     * @param $order
     * @param $addressObj
     * @param $preparedOrder
     */
    private function setCustomer($order, $addressObj, &$preparedOrder)
    {
        if ($order->getCustomerIsGuest() == 1) {
            $customer = $this->getCustomerByEmail($order->getCustomerEmail());

            if ($customer !== false) {
                $preparedOrder['customer']['id'] = $customer['id'];
            }
        }

        if ($order->getCustomerIsGuest() == 0) {
            if ($this->existsInCrm($order->getCustomerId(), 'customersGet')) {
                $preparedOrder['customer']['externalId'] = $order->getCustomerId();
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
                    $preparedOrder['customer']['externalId'] = $order->getCustomerId();
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
     *
     * @return boolean
     */
    private function existsInCrm($id, $method = 'ordersGet', $by = 'externalId')
    {
        $response = $this->api->{$method}($id, $by);

        if ($response === false) {
            return;
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
}
