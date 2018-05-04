<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Payment extends \Magento\Config\Block\System\Config\Form\Field
{
    private $systemStore;
    private $formFactory;
    private $config;
    private $paymentConfig;
    private $client;

    public function __construct(
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Payment\Model\Config $paymentConfig,
        \Retailcrm\Retailcrm\Helper\Proxy $client
    ) {
        $this->systemStore = $systemStore;
        $this->formFactory = $formFactory;
        $this->config = $config;
        $this->paymentConfig = $paymentConfig;
        $this->client = $client;
    }

    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';

        if ($this->client->isConfigured()) {
            $activePaymentMethods = $this->paymentConfig->getActiveMethods();
            $response = $this->client->paymentTypesList();

            if ($response === false) {
                return $htmlError;
            }

            if ($response->isSuccessful()) {
                $paymentTypes = $response['paymentTypes'];
            } else {
                return $htmlError;
            }

            foreach ($activePaymentMethods as $code => $payment) {
                $html .= '<table id="' . $element->getId() . '_table">';
                $html .= '<tr id="row_retailcrm_payment_' . $code . '">';
                $html .= '<td class="label">' . $payment->getTitle() . '</td>';
                $html .= '<td>';
                $html .= '<select id="1" name="groups[Payment][fields][' . $code . '][value]">';

                $selected = $this->config->getValue('retailcrm/Payment/' . $code);

                foreach ($paymentTypes as $k => $value) {
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
