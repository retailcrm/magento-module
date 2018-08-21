<?php

namespace Retailcrm\Retailcrm\Model\Service;

use \Retailcrm\Retailcrm\Helper\Data as Helper;

class Order implements \Retailcrm\Retailcrm\Api\OrderManagerInterface
{
    private $productRepository;
    private $config;
    private $helper;
    private $configurableProduct;

    public function __construct(
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurableProduct,
        Helper $helper
    ) {
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->helper = $helper;
        $this->configurableProduct = $configurableProduct;
    }

    /**
     * Process order
     *
     * @param \Magento\Sales\Model\Order $order
     *
     * @return mixed
     */
    public function process(\Magento\Sales\Model\Order $order)
    {
        $items = $order->getAllItems();
        $products = $this->addProducts($items);
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $shipping = $this->getShippingCode($order->getShippingMethod());

        $preparedOrder = [
            'externalId' => $order->getId(),
            'number' => $order->getRealOrderId(),
            'createdAt' => $order->getCreatedAt(),
            'lastName' => $order->getCustomerLastname(),
            'firstName' => $order->getCustomerFirstname(),
            'patronymic' => $order->getCustomerMiddlename(),
            'email' => $order->getCustomerEmail(),
            'phone' => $billingAddress->getTelephone(),
            'status' => $this->config->getValue('retailcrm/retailcrm_status/' . $order->getStatus()),
            'items' => $products,
            'delivery' => [
                'code' => $this->config->getValue('retailcrm/retailcrm_shipping/' . $shipping),
                'cost' => $order->getShippingAmount(),
                'address' => [
                    'index' => $shippingAddress->getData('postcode'),
                    'city' => $shippingAddress->getData('city'),
                    'countryIso' => $shippingAddress->getData('country_id'),
                    'street' => $shippingAddress->getData('street'),
                    'region' => $shippingAddress->getData('region'),
                    'text' => trim(
                        ',',
                        implode(
                            ',',
                            [
                                $shippingAddress->getData('postcode'),
                                $shippingAddress->getData('city'),
                                $shippingAddress->getData('street'),
                            ]
                        )
                    )
                ]
            ]
        ];

        if ($billingAddress->getData('country_id')) {
            $preparedOrder['countryIso'] = $billingAddress->getData('country_id');
        }

        if ($this->helper->getGeneralSettings('api_version') == 'v4') {
            $preparedOrder['discount'] = abs($order->getDiscountAmount());
            $preparedOrder['paymentType'] = $this->config->getValue(
                'retailcrm/retailcrm_payment/' . $order->getPayment()->getMethodInstance()->getCode()
            );
        } elseif ($this->helper->getGeneralSettings('api_version') == 'v5') {
            $preparedOrder['discountManualAmount'] = abs($order->getDiscountAmount());
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

            $preparedOrder['payments'][] = $payment;
        }

        if ($order->getCustomerIsGuest() == 0) {
            $preparedOrder['customer']['externalId'] = $order->getCustomerId();
        }

        return Helper::filterRecursive($preparedOrder);
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
     * Add products in order array
     *
     * @param $items
     *
     * @return array
     */
    protected function addProducts($items)
    {
        $products = [];

        foreach ($items as $item) {
            if ($item->getProductType() == 'configurable') {
                $attributesInfo = $item->getProductOptions()['attributes_info'];
                $attributes = [];

                foreach ($attributesInfo as $attributeInfo) {
                    $attributes[$attributeInfo['option_id']] = $attributeInfo['option_value'];
                }

                $product = $this->configurableProduct->getProductByAttributes($attributes, $item->getProduct());
            } else {
                $product = $item->getProduct();
            }

            $price = $item->getPrice();

            if ($price == 0) {
                $magentoProduct = $this->productRepository->getById($item->getProductId());
                $price = $magentoProduct->getPrice();
            }

            $resultProduct = [
                'productName' => $item->getName(),
                'quantity' => $item->getQtyOrdered(),
                'initialPrice' => $price,
                'offer' => [
                    'externalId' => $product ? $product->getId() : ''
                ]
            ];

            unset($magentoProduct);
            unset($price);

            $products[] = $resultProduct;
        }

        return $products;
    }
}
