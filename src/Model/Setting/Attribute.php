<?php

namespace Retailcrm\Retailcrm\Model\Setting;

class Attribute implements \Magento\Framework\Option\ArrayInterface
{
    private $entityType;
    private $store;

    public function __construct(
        \Magento\Store\Model\Store $store,
        \Magento\Eav\Model\Entity\Type $entityType
    ) {
        $this->store = $store;
        $this->entityType = $entityType;
    }

    public function toOptionArray()
    {
        $types = [
            'text',
            'decimal',
            'boolean',
            'select',
            'price'
        ];
        $attributes = $this->entityType->loadByCode('catalog_product')->getAttributeCollection();
        $attributes->addFieldToFilter('frontend_input', $types);

        $result = [];

        foreach ($attributes as $attr) {
            if ($attr->getFrontendLabel()) {
                $result[] = [
                    'value' => $attr->getAttributeCode(),
                    'label' => $attr->getFrontendLabel(),
                    'title' => $attr->getAttributeCode()
                ];
            }
        }

        return $result;
    }
}
