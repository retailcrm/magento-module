<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset;

class StatusList extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * @var $_statusCms \Retailcrm\Retailcrm\Model\Config\Backend\StatusCms
     */
    protected $_statusCms;

    /**
     * @var $_statusCrm \Retailcrm\Retailcrm\Model\Config\Backend\StatusCrm
     */
    protected $_statusCrm;

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getStatusCmsRenderer()
    {
        if (!$this->_statusCms) {
            $this->_statusCms = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\StatusCms',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_statusCms;
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getStatusCrmRenderer()
    {
        if (!$this->_statusCrm) {
            $this->_statusCrm = $this->getLayout()->createBlock(
                '\Retailcrm\Retailcrm\Model\Config\Backend\StatusCrm',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->_statusCrm;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'status_cms',
            [
                'label' => __('CMS'),
                'renderer' => $this->_getStatusCmsRenderer()
            ]
        );

        $this->addColumn(
            'status_crm',
            [
                'label' => __('CRM'),
                'renderer' => $this->_getStatusCrmRenderer()
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
        $customAttribute = $row->getData('status_cms');
        $key = 'option_' . $this->_getStatusCmsRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $customAttribute = $row->getData('status_crm');
        $key = 'option_' . $this->_getStatusCrmRenderer()->calcOptionHash($customAttribute);
        $options[$key] = 'selected="selected"';

        $row->setData('option_extra_attrs', $options);
    }
}
