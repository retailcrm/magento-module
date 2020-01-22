<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset;

class ShippingList extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * @var $_shippingCms \Retailcrm\Retailcrm\Model\Config\Backend\ShippingCms
     */
    protected $_shippingCms;

    /**
     * @var $_shippingCrm \Retailcrm\Retailcrm\Model\Config\Backend\ShippingCrm
     */
    protected $_shippingCrm;

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getShippingCmsRenderer()
    {
        if (!$this->_shippingCms) {
            $this->_shippingCms = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\ShippingCms',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_shippingCms;
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getShippingCrmRenderer()
    {
        if (!$this->_shippingCrm) {
            $this->_shippingCrm = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\ShippingCrm',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_shippingCrm;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'shipping_cms',
            [
                'label' => __('CMS'),
                'renderer' => $this->_getShippingCmsRenderer()
            ]
        );

        $this->addColumn(
            'shipping_crm',
            [
                'label' => __('CRM'),
                'renderer' => $this->_getShippingCrmRenderer()
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     *
     * @param \Magento\Framework\DataObject $row
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $options = [];
        $customAttribute = $row->getData('shipping_cms');
        $key = 'option_' . $this->_getShippingCmsRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $customAttribute = $row->getData('shipping_crm');
        $key = 'option_' . $this->_getShippingCrmRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $row->setData('option_extra_attrs', $options);
    }
}
