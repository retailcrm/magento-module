<?php

namespace Retailcrm\Retailcrm\Model\Setting;

class Shipping implements \Magento\Framework\Option\ArrayInterface
{
    private $entityType;
    private $store;
    private $config;
    private $shippingConfig;

    public function __construct(
        \Magento\Store\Model\Store $store,
        \Magento\Eav\Model\Entity\Type $entityType,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Shipping\Model\Config $shippingConfig
    ) {
        $this->store = $store;
        $this->entityType = $entityType;
        $this->config = $config;
        $this->shippingConfig = $shippingConfig;
    }

    public function toOptionArray()
    {
        $activeShipping = $this->shippingConfig->getActiveCarriers();

        foreach ($activeShipping as $carrierCode => $carrierModel) {
            $options = [];

            if ($carrierModel->getAllowedMethods()) {
                $carrierMethods = $carrierModel->getAllowedMethods();

                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = [
                        'value' => $code,
                        'label' => $method
                    ];
                }

                $carrierTitle = $this->config->getValue('carriers/' . $carrierCode . '/title');
            }

            $methods[] = [
                'value' => $options,
                'label' => $carrierTitle
            ];
        }

        return $methods;
    }
}
