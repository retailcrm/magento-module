<?php
class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Payment extends Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Base
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);

        if(!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {
            $groups = Mage::getSingleton('payment/config')->getActiveMethods();

            foreach ($groups as $group) {
                $html .= $this->_getFieldHtml($element, $group);
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
        if(!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {
            $client = Mage::getModel(
                'retailcrm/ApiClient',
                array('url' => $this->_apiUrl, 'key' => $this->_apiKey, 'site' => null)
            );

            try {
                $paymentTypes = $client->paymentTypesList();
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log($e->getMessage());
            }

            if ($paymentTypes->isSuccessful()) {
                if (empty($this->_values)) {
                    foreach ($paymentTypes['paymentTypes'] as $type) {
                        $this->_values[] = array('label' => Mage::helper('adminhtml')->__($type['name']), 'value' => $type['code']);
                    }
                }
            }
        }

        return $this->_values;
    }

    protected function _getFieldHtml($fieldset, $group)
    {
        $configData = $this->getConfigData();

        $path = 'retailcrm/payment/' . $group->getId();
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }


        $field = $fieldset->addField('payment_' . $group->getId(), 'select',
            array(
                'name'          => 'groups[payment][fields]['.$group->getId().'][value]',
                'label'         => Mage::getStoreConfig('payment/'.$group->getId().'/title'),
                'value'         => $data,
                'values'        => $this->_getValues(),
                'inherit'       => $inherit,
                'can_use_default_value' => 1,
                'can_use_website_value' => 1
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}
