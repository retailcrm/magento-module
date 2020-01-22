<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset;

class PaymentList extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * @var $_paymentCms \Retailcrm\Retailcrm\Model\Config\Backend\PaymentCms
     */
    protected $_paymentCms;

    /**
     * @var $_paymentCrm \Retailcrm\Retailcrm\Model\Config\Backend\PaymentCrm
     */
    protected $_paymentCrm;

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getPaymentCmsRenderer()
    {
        if (!$this->_paymentCms) {
            $this->_paymentCms = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\PaymentCms',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_paymentCms;
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getPaymentCrmRenderer()
    {
        if (!$this->_paymentCrm) {
            $this->_paymentCrm = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\PaymentCrm',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_paymentCrm;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'payment_cms',
            [
                'label' => __('CMS'),
                'renderer' => $this->_getPaymentCmsRenderer()
            ]
        );

        $this->addColumn(
            'payment_crm',
            [
                'label' => __('CRM'),
                'renderer' => $this->_getPaymentCrmRenderer()
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
        $customAttribute = $row->getData('payment_cms');
        $key = 'option_' . $this->_getPaymentCmsRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $customAttribute = $row->getData('payment_crm');
        $key = 'option_' . $this->_getPaymentCrmRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $row->setData('option_extra_attrs', $options);
    }
}
