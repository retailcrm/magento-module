<?php

namespace Retailcrm\Retailcrm\Model\Order;

use RetailCrm\Retailcrm\Helper\Data as Helper;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;
use Retailcrm\Retailcrm\Model\Observer\OrderCreate;

class OrderNumber extends OrderCreate
{
    private $salesOrder;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Magento\Catalog\Model\ProductRepository $product,
        Helper $helper,
        ApiClient $api,
        \Magento\Sales\Api\Data\OrderInterface $salesOrder
    ) {
        $this->salesOrder = $salesOrder;
        parent::__construct($config, $registry, $logger, $product, $helper, $api);
    }

    public function exportOrderNumber()
    {
        $ordernumber = $this->config->getValue('retailcrm/Load/number_order');
        $ordersId = explode(",", $ordernumber);
        $orders = [];

        foreach ($ordersId as $id) {
            $magentoOrder = $this->salesOrder->load($id);
            $orders[$magentoOrder->getStore()->getId()][] = $this->prepareOrder($magentoOrder);
        }

        foreach ($orders as $storeId => $ordersStore) {
            $chunked = array_chunk($ordersStore, 50);
            unset($ordersStore);

            foreach ($chunked as $chunk) {
                $this->api->setSite($this->helper->getSite($storeId));
                $this->api->ordersUpload($chunk);
                time_nanosleep(0, 250000000);
            }

            unset($chunked);
        }

        return true;
    }

    public function prepareOrder($magentoOrder)
    {
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

        return Helper::filterRecursive($preparedOrder);
    }
}
