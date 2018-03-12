<?php

namespace Retailcrm\Retailcrm\Model\Setting;

class Shipping implements \Magento\Framework\Option\ArrayInterface
{
    protected $_entityType;
    protected $_store;

    public function __construct(
        \Magento\Store\Model\Store $store,
        \Magento\Eav\Model\Entity\Type $entityType
    ) {
        $this->_store = $store;
        $this->_entityType = $entityType;
    }

    public function toOptionArray()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $activeShipping = $objectManager->create('Magento\Shipping\Model\Config')->getActiveCarriers();

        $config = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');

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

                $carrierTitle = $config->getValue('carriers/' . $carrierCode . '/title');
            }

            $methods[] = [
                'value' => $options,
                'label' => $carrierTitle
            ];
        }

        return $methods;
    }
}
