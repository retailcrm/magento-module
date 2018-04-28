<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Shipping extends \Magento\Config\Block\System\Config\Form\Field
{
    private $systemStore;
    private $formFactory;
    private $config;
    private $shippingConfig;
    private $client;

    public function __construct(
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Retailcrm\Retailcrm\Helper\Proxy $client
    ) {
        $this->systemStore = $systemStore;
        $this->formFactory = $formFactory;
        $this->config = $config;
        $this->shippingConfig = $shippingConfig;
        $this->client = $client;
    }

    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';

        if ($this->client->isConfigured()) {
            $deliveryMethods = $this->shippingConfig->getActiveCarriers();
            $response = $this->client->deliveryTypesList();

            if ($response === false) {
                return $htmlError;
            }

            if ($response->isSuccessful()) {
                $deliveryTypes = $response['deliveryTypes'];
            } else {
                return $htmlError;
            }

            foreach (array_keys($deliveryMethods) as $k => $delivery) {
                $html .= '<table id="' . $element->getId() . '_table">';
                $html .= '<tr id="row_retailcrm_shipping_'.$delivery.'">';
                $html .= '<td class="label">'.$delivery.'</td>';
                $html .= '<td>';
                $html .= '<select id="1" name="groups[Shipping][fields]['.$delivery.'][value]">';

                $selected = $this->config->getValue('retailcrm/Shipping/'.$delivery);

                foreach ($deliveryTypes as $k => $value) {
                    if (!empty($selected) && $selected == $value['code']) {
                        $select = 'selected="selected"';
                    } else {
                        $select = '';
                    }

                    $html .= '<option ' . $select . ' value="' . $value['code'] . '"> ' . $value['name'] . '</option>';
                }

                $html .= '</select>';
                $html .= '</td>';
                $html .= '</tr>';
                $html .= '</table>';
            }

            return $html;
        } else {
            return $htmlError;
        }
    }
}
