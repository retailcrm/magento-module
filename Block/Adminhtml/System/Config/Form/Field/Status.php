<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Status extends \Magento\Config\Block\System\Config\Form\Field
{
    private $systemStore;
    private $formFactory;
    private $config;
    private $statusCollection;
    private $client;

    public function __construct(
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection,
        \Retailcrm\Retailcrm\Helper\Proxy $client
    ) {
        $this->systemStore = $systemStore;
        $this->formFactory = $formFactory;
        $this->config = $config;
        $this->statusCollection = $statusCollection;
        $this->client = $client;
    }

    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';

        if ($this->client->isConfigured()) {
            $statuses = $this->statusCollection->toOptionArray();
            $response = $this->client->statusesList();

            if ($response === false) {
                return $htmlError;
            }

            if ($response->isSuccessful()) {
                $statusTypes = $response['statuses'];
            } else {
                return $htmlError;
            }

            foreach ($statuses as $k => $status) {
                $html .= '<table id="' . $element->getId() . '_table">';
                $html .= '<tr id="row_retailcrm_status_' . $status['label'] . '">';
                $html .= '<td class="label">' . $status['label'] . '</td>';
                $html .= '<td>';
                $html .= '<select name="groups[Status][fields][' . $status['value'] . '][value]">';

                $selected = $this->config->getValue('retailcrm/Status/' . $status['value']);

                $html .= '<option value=""> Select status </option>';

                foreach ($statusTypes as $k => $value) {
                    if ((!empty($selected)
                        && $selected == $value['name'])
                        || $selected == $value['code']
                    ) {
                        $select = 'selected="selected"';
                    } else {
                        $select = '';
                    }

                    $html .= '<option ' . $select . 'value="' . $value['code'] . '"> ' . $value['name'] . '</option>';
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
