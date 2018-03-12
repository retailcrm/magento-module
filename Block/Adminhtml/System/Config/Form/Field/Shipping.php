<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class Shipping extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_apiUrl;
    protected $_apiKey;
    protected $_systemStore;
    protected $_formFactory;
    protected $_logger;
    protected $_objectManager;

    public function __construct(
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore
    ) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $this->_apiUrl = $config->getValue('retailcrm/general/api_url');
        $this->_apiKey = $config->getValue('retailcrm/general/api_key');
        $this->_apiVersion = $config->getValue('retailcrm/general/api_version');
        $this->_systemStore = $systemStore;
        $this->_formFactory = $formFactory;
        $this->_objectManager = $objectManager;
    }

    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';

        if (!empty($this->_apiUrl) && !empty($this->_apiKey)) {
            $shipConfig = $this->_objectManager->get('Magento\Shipping\Model\Config');
            $deliveryMethods = $shipConfig->getActiveCarriers();

            $client = new ApiClient($this->_apiUrl, $this->_apiKey, $this->_apiVersion);

            $response = $client->deliveryTypesList();

            if ($response === false) {
                return $htmlError;
            }

            if ($response->isSuccessful()) {
                $deliveryTypes = $response['deliveryTypes'];
            } else {
                return $htmlError;
            }

            $config = \Magento\Framework\App\ObjectManager::getInstance()->get(
                'Magento\Framework\App\Config\ScopeConfigInterface'
            );

            foreach (array_keys($deliveryMethods) as $k => $delivery) {
                $html .='<table id="' . $element->getId() . '_table">';
                $html .='<tr id="row_retailcrm_shipping_'.$delivery.'">';
                $html .='<td class="label">'.$delivery.'</td>';
                $html .='<td>';
                $html .='<select id="1" name="groups[Shipping][fields]['.$delivery.'][value]">';

                $selected = $config->getValue('retailcrm/Shipping/'.$delivery);

                foreach ($deliveryTypes as $k => $value) {
                    if ((!empty($selected)) && (($selected == $value['code']))) {
                        $select ='selected="selected"';
                    }else{
                        $select ='';
                    }

                    $html .='<option '.$select.' value="'.$value['code'].'"> '.$value['name'].'</option>';
                }

                $html .='</select>';
                $html .='</td>';
                $html .='</tr>';
                $html .='</table>';
            }

            return $html;
        } else {
            return $htmlError;
        }
    }
}
