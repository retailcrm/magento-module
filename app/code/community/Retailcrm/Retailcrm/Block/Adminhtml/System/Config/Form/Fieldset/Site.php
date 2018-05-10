<?php

class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Site extends Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Base
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);

        if (!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {
                $html .= $this->_getFieldHtml($element);
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
        if (!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {
            $client = new Retailcrm_Retailcrm_Model_ApiClient(
                $this->_apiUrl,
                $this->_apiKey
            );

            try {
                $sites = $client->sitesList();
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log($e->getMessage());
            }

            if ($sites->isSuccessful()) {
                if (empty($this->_values)) {
                    foreach ($sites['sites'] as $site) {
                        $this->_values[] = array('label'=>Mage::helper('adminhtml')->__($site['name']), 'value'=>$site['code']);
                    }
                }
            }
        }

        return $this->_values;
    }

    protected function _getFieldHtml($fieldset)
    {
        $configData = $this->getConfigData();

        $path = 'retailcrm/site/default';
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int)(string)$this->getForm()->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $field = $fieldset->addField(
            'site_default', 'select',
            array(
                'name'          => 'groups[site][fields][default][value]',
                'label'         => 'Default',
                'value'         => $data,
                'values'        => $this->_getValues(),
                'inherit'       => $inherit,
                'can_use_default_value' => 1,
                'can_use_website_value' => 1
            )
        )->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}