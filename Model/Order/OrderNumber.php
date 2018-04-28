<?php

namespace Retailcrm\Retailcrm\Model\Order;

use Retailcrm\Retailcrm\Model\Observer\OrderCreate;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderNumber extends OrderCreate
{
    private $orderRepository;
    private $searchCriteriaBuilder;
    private $config;
    private $filterBuilder;
    private $order;
    private $api;
    private $logger;
    private $productRepository;

    public function __construct(
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        ApiClient $api
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->filterBuilder = $filterBuilder;
        $this->order = $order;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->api = $api;
    }

    public function exportOrderNumber()
    {
        $ordernumber = $this->config->getValue('retailcrm/Load/number_order');
        $ordersId = explode(",", $ordernumber);
        $orders = [];

        foreach ($ordersId as $id) {
            $orders[] = $this->prepareOrder($id);
        }

        $chunked = array_chunk($orders, 50);
        unset($orders);

        foreach ($chunked as $chunk) {
            $this->api->ordersUpload($chunk);
            time_nanosleep(0, 250000000);
        }

        unset($chunked);

        return true;
    }

    public function prepareOrder($id)
    {
        $magentoOrder = $this->order->load($id);

        $items = [];
        $addressObj = $magentoOrder->getBillingAddress();

        foreach ($magentoOrder->getAllItems() as $item) {
            if ($item->getProductType() == "simple") {
                $price = $item->getPrice();

                if ($price == 0) {
                    $magentoProduct = $this->productRepository->getById($item->getProductId());
                    $price = $magentoProduct->getPrice();
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

                unset($magentoProduct);
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
            'paymentType' => $this->config->getValue(
                'retailcrm/Payment/' . $magentoOrder->getPayment()->getMethodInstance()->getCode()
            ),
            'status' => $this->config->getValue('retailcrm/Status/'.$magentoOrder->getStatus()),
            'discount' => abs($magentoOrder->getDiscountAmount()),
            'items' => $items,
            'delivery' => [
                'code' => $this->config->getValue('retailcrm/Shipping/'.$ship),
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

        if (trim($preparedOrder['delivery']['code']) == '') {
            unset($preparedOrder['delivery']['code']);
        }

        if (trim($preparedOrder['paymentType']) == '') {
            unset($preparedOrder['paymentType']);
        }

        if (trim($preparedOrder['status']) == '') {
            unset($preparedOrder['status']);
        }

        if ($magentoOrder->getCustomerIsGuest() == 0) {
            $preparedOrder['customer']['externalId'] = $magentoOrder->getCustomerId();
        }

        $this->logger->writeDump($preparedOrder, 'OrderNumber');

        return \Retailcrm\Retailcrm\Helper\Data::filterRecursive($preparedOrder);
    }
}
