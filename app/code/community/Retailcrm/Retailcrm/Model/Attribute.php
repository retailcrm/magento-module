<?php
class Retailcrm_Retailcrm_Model_Attribute
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->getItems();

        $data = array();

        foreach($attributes as $attribute) {
            if(empty($attribute->getFrontendLabel())) continue;

            $data[] = array(
                'label' => $attribute->getFrontendLabel(),
                'value' => $attribute->getAttributeCode()
            );
        }

        return $data;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array();

        return array(
            0 => Mage::helper('adminhtml')->__('Data1'),
            1 => Mage::helper('adminhtml')->__('Data2'),
            2 => Mage::helper('adminhtml')->__('Data3'),
        );
    }
}
