<?php
class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Status extends Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Base
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $warn = '
            <div style="margin-left: 15px;">
                <b><i>Please check your API Url & API Key</i></b>
            </div>
        ';

        if(!empty($this->_apiUrl) && !empty($this->_apiKey) && $this->_isCredentialCorrect) {
            $orderEntity = Mage::getModel('sales/order');
            $reflection  = new ReflectionClass($orderEntity);
            $constants = $reflection->getConstants();
            $values = array();

            foreach ($constants as $key => $value)
            {
                if (preg_match("/state_/i", $key)) {
                    $label = implode(
                        ' ',
                        $arr = array_map(
                            function($word) {
                               return ucfirst($word);
                            },
                            explode('_', $value)
                        )
                    );

                    $values[] = array('code' => $value, 'name' => $label);
                }
            }

            if (!empty($values)) {
                foreach ($values as $group) {
                    $html.= $this->_getFieldHtml($element, $group);
                }
            }

        } else {
            $html .= $warn;
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton(
                'adminhtml/system_config_form_field'
            );
        }
        return $this->_fieldRenderer;
    }

    /**
     * @return array
     */
    protected function _getValues()
    {
        $client = Mage::getModel(
            'retailcrm/ApiClient',
            array(
                'url' => $this->_apiUrl,
                'key' => $this->_apiKey,
                'site' => null
            )
        );

        try {
            $statuses = $client->statusesList();
        } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
            Mage::log($e->getMessage());
        }

        if (empty($this->_values)) {
            $this->_values[] = array(
                'label' => Mage::helper('adminhtml')
                    ->__('===Select status==='),
                'value' => ''
            );
            if ($statuses->isSuccessful() && !empty($statuses)) {
                foreach ($statuses['statuses'] as $status) {
                    $this->_values[] = array(
                        'label' => Mage::helper('adminhtml')
                            ->__($status['name']),
                        'value' => $status['code']
                    );
                }
            }
        }

        return $this->_values;
    }

    protected function _getFieldHtml($fieldset, $group)
    {
        $configData = $this->getConfigData();

        $path = 'retailcrm/status/'.$group['code'];

        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            $data = (int) (string) $this->getForm()
                ->getConfigRoot()->descend($path);
            $inherit = true;
        }

        $field = $fieldset->addField($group['code'], 'select',
            array(
                'name' => 'groups[status][fields]['.$group['code'].'][value]',
                'label' => $group['name'],
                'value' => $data,
                'values' => $this->_getValues(),
                'inherit' => $inherit,
                'can_use_default_value' => 1,
                'can_use_website_value' => 1
            ))->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}
