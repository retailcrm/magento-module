<?php
class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Paymentstatus extends Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Base
{

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);

        if(!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {

            $client = Mage::getModel(
                'retailcrm/ApiClient',
                array('url' => $this->_apiUrl, 'key' => $this->_apiKey, 'site' => null)
            );

            try {
                $paymentStatuses = $client->paymentStatusesList();
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log($e->getMessage());
            }

            if ($paymentStatuses->isSuccessful() && !empty($paymentStatuses)) {
                foreach ($paymentStatuses['paymentStatuses'] as $group) {
                    $html.= $this->_getFieldHtml($element, $group);
                }
            }
        } else {
            $html .= '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    /**
     * @return array
     */
    protected function _getValues()
    {
        $values = Mage::getModel('sales/order_status')->getResourceCollection()->getData();
        if (empty($this->_values)) {
            $this->_values[] = array('label'=>Mage::helper('adminhtml')->__('===Select status==='), 'value'=>'');
            foreach ($values as $value) {
                $this->_values[] = array('label'=>Mage::helper('adminhtml')->__($value['label']), 'value'=>$value['status']);
            }
        }

        return $this->_values;
    }

    protected function _getFieldHtml($fieldset, $group)
    {
        $configData = $this->getConfigData();

        $path = 'retailcrm/paymentstatus/'.$group['code'];

        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $field = $fieldset->addField($group['code'], 'select',
            array(
                'name'          => 'groups[paymentstatus][fields]['.$group['code'].'][value]',
                'label'         => $group['name'],
                'value'         => $data,
                'values'        => $this->_getValues(),
                'inherit'       => $inherit,
                'can_use_default_value' => 1,
                'can_use_website_value' => 1
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}
