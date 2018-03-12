<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderCreate implements \Magento\Framework\Event\ObserverInterface
{
    protected $_api;
    protected $_objectManager;
    protected $_config;
    protected $_helper;
    protected $_logger;
    protected $_registry;

    /**
     * Constructor
     * 
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Retailcrm\Retailcrm\Model\Logger\Logger $logger
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry
    ) {
        $helper = $objectManager->get('\Retailcrm\Retailcrm\Helper\Data');
        $this->_logger = $objectManager->get('\Retailcrm\Retailcrm\Model\Logger\Logger');
        $this->_helper = $helper;
        $this->_objectManager = $objectManager;
        $this->_config = $config;
        $this->_registry = $registry;

        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        $apiVersion = $config->getValue('retailcrm/general/api_version');

        if (!empty($url) && !empty($key)) {
            $this->_api = new ApiClient($url, $key, $apiVersion);
        }
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
        if ($this->_registry->registry('RETAILCRM_HISTORY') === true) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        
        if ($this->existsInCrm($order->getId()) === true) {
            return;
        }

        $items = [];
        $addressObj = $order->getBillingAddress();

        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() == "simple") {
                $price = $item->getPrice();

                if ($price == 0) {
                    $omproduct = $this->_objectManager->get('Magento\Catalog\Model\ProductRepository')
                        ->getById($item->getProductId());
                    $price = $omproduct->getPrice();
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

                unset($omproduct);
                unset($price);

                $items[] = $product;
            }  
        }

        $shippingCode = $this->getShippingCode($order->getShippingMethod());

        $preparedOrder = [
            'site' => $order->getStore()->getCode(),
            'externalId' => $order->getId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'lastName' => $order->getCustomerLastname() ? $order->getCustomerLastname() : $addressObj->getLastname(),
            'firstName' => $order->getCustomerFirstname() ? $order->getCustomerFirstname() : $addressObj->getFirstname(),
            'patronymic' => $order->getCustomerMiddlename() ? $order->getCustomerMiddlename() : $addressObj->getMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $addressObj->getTelephone(),
            'status' => $this->_config->getValue('retailcrm/Status/' . $order->getStatus()),
            'items' => $items,
            'delivery' => [
                'code' => $this->_config->getValue('retailcrm/Shipping/' . $shippingCode),
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

        if ($this->_api->getVersion() == 'v4') {
            $preparedOrder['paymentType'] = $this->_config->getValue('retailcrm/Payment/'.$order->getPayment()->getMethodInstance()->getCode());
            $preparedOrder['discount'] = abs($order->getDiscountAmount());
        } elseif ($this->_api->getVersion() == 'v5') {
            $preparedOrder['discountManualAmount'] = abs($order->getDiscountAmount());

            $payment = [
                'type' => $this->_config->getValue('retailcrm/Payment/' . $order->getPayment()->getMethodInstance()->getCode()),
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

        if ($order->getCustomerIsGuest() == 1) {
            $customer = $this->getCustomerByEmail($order->getCustomerEmail());

            if ($customer !== false) {
                $preparedOrder['customer']['id'] = $customer['id'];
            }
        } elseif ($order->getCustomerIsGuest() == 0) {
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

                if ($this->_api->customersCreate($preparedCustomer)) {
                    $preparedOrder['customer']['externalId'] = $order->getCustomerId();
                }
            }
        }

        $this->_helper->filterRecursive($preparedOrder);

        $this->_logger->writeDump($preparedOrder,'CreateOrder');

        $this->_api->ordersCreate($preparedOrder);

        return $this;
    }    

    /**
     * Get shipping code
     * 
     * @param string $string
     * 
     * @return string
     */
    protected function getShippingCode($string)
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
    protected function existsInCrm($id, $method = 'ordersGet', $by = 'externalId')
    {
        $response = $this->_api->{$method}($id, $by);

        if ($response === false) {
            return;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->_api->getErrorText('errorNotFound')) {
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
    protected function getCustomerByEmail($email)
    {
        $response = $this->_api->customersList(['email' => $email]);

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
