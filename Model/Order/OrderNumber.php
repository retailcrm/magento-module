<?php

namespace Retailcrm\Retailcrm\Model\Order;

use Retailcrm\Retailcrm\Model\Observer\OrderCreate;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderNumber extends OrderCreate
{
    protected $_orderRepository;
    protected $_searchCriteriaBuilder;
    protected $_config;
    protected $_filterBuilder;
    protected $_order;
    protected $_helper;
    protected $_api;
    protected $_logger;
    
    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderRepository = $objectManager->get('Magento\Sales\Model\OrderRepository');
        $searchCriteriaBuilder = $objectManager->get('Magento\Framework\Api\SearchCriteriaBuilder');
        $config = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
        $filterBuilder = $objectManager->get('Magento\Framework\Api\FilterBuilder');
        $order = $objectManager->get('\Magento\Sales\Api\Data\OrderInterface');
        $helper = $objectManager->get('\Retailcrm\Retailcrm\Helper\Data');

        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_config = $config;
        $this->_filterBuilder = $filterBuilder;
        $this->_order = $order;
        $this->_helper = $helper;
        $this->_logger = $objectManager->get('\Retailcrm\Retailcrm\Model\Logger\Logger');

        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        $version = $config->getValue('retailcrm/general/api_version');

        if (!empty($url) && !empty($key)) {
            $this->_api = new ApiClient($url, $key, $version);
        }
    }

    public function ExportOrderNumber()
    {
        $ordernumber = $this->_config->getValue('retailcrm/Load/number_order');
        $ordersId = explode(",", $ordernumber);
        $orders = [];

        foreach ($ordersId as $id) {
            $orders[] = $this->prepareOrder($id);
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

    public function prepareOrder($id)
    {
        $magentoOrder = $this->_order->load($id);

        $items = [];
        $addressObj = $magentoOrder->getBillingAddress();

        foreach ($magentoOrder->getAllItems() as $item) {
            if ($item->getProductType() == "simple") {
                $price = $item->getPrice();

                if ($price == 0){
                    $om = \Magento\Framework\App\ObjectManager::getInstance();
                    $omproduct = $om->get('Magento\Catalog\Model\ProductRepository')
                        ->getById($item->getProductId());
                    $price = $omproduct->getPrice();
                }

                $product = [
                    'productId' => $item->getProductId(),
                    'productName' => $item->getName(),
                    'quantity' => $item->getQtyOrdered(),
                    'initialPrice' => $price,
                    'offer' => [
                        'externalId'=>$item->getProductId()
                    ]
                ];

                unset($om);
                unset($omproduct);
                unset($price);

                $items[] = $product;
            }
        }

        $ship = $this->getShippingCode($magentoOrder->getShippingMethod());

        $preparedOrder = [
            'site' => $magentoOrder->getStore()->getCode(),
            'externalId' => $magentoOrder->getRealOrderId(),
            'number' => $magentoOrder->getRealOrderId(),
            'createdAt' => date('Y-m-d H:i:s'),
            'lastName' => $magentoOrder->getCustomerLastname(),
            'firstName' => $magentoOrder->getCustomerFirstname(),
            'patronymic' => $magentoOrder->getCustomerMiddlename(),
            'email' => $magentoOrder->getCustomerEmail(),
            'phone' => $addressObj->getTelephone(),
            'paymentType' => $this->_config->getValue('retailcrm/Payment/'.$magentoOrder->getPayment()->getMethodInstance()->getCode()),
            'status' => $this->_config->getValue('retailcrm/Status/'.$magentoOrder->getStatus()),
            'discount' => abs($magentoOrder->getDiscountAmount()),
            'items' => $items,
            'delivery' => [
                'code' => $this->_config->getValue('retailcrm/Shipping/'.$ship),
                'cost' => $magentoOrder->getShippingAmount(),
                'address' => [
                    'index' => $addressObj->getData('postcode'),
                    'city' => $addressObj->getData('city'),
                    'country' => $addressObj->getData('country_id'),
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

        if (trim($preparedOrder['delivery']['code']) == ''){
            unset($preparedOrder['delivery']['code']);
        }

        if (trim($preparedOrder['paymentType']) == ''){
            unset($preparedOrder['paymentType']);
        }

        if (trim($preparedOrder['status']) == ''){
            unset($preparedOrder['status']);
        }

        if ($magentoOrder->getCustomerIsGuest() == 0) {
            $preparedOrder['customer']['externalId'] = $magentoOrder->getCustomerId();
        }

        $this->_logger->writeDump($preparedOrder,'OrderNumber');

        return $this->_helper->filterRecursive($preparedOrder);
    }
}
