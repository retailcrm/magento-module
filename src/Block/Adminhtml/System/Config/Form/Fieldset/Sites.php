<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Sites extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * Dummy element
     *
     * @var \Magento\Framework\DataObject
     */
    protected $_dummyElement;

    /**
     * Field renderer
     *
     * @var \Magento\Config\Block\System\Config\Form\Field
     */
    protected $_fieldRenderer;

    private $storeManager;
    private $client;

    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Retailcrm\Retailcrm\Helper\Proxy $client,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->client = $client;

        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * Get field renderer
     *
     * @return \Magento\Config\Block\System\Config\Form\Field
     */
    protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = $this->getLayout()->getBlockSingleton(
                \Magento\Config\Block\System\Config\Form\Field::class
            );
        }

        return $this->_fieldRenderer;
    }

    /**
     * Get dummy element
     *
     * @return \Magento\Framework\DataObject
     */
    protected function _getDummyElement()
    {
        if (empty($this->_dummyElement)) {
            $this->_dummyElement = new \Magento\Framework\DataObject(['showInDefault' => 1, 'showInWebsite' => 0]);
        }

        return $this->_dummyElement;
    }

    /**
     * Render element
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';
        $html .= $this->_getHeaderHtml($element);

        if ($this->client->isConfigured()) {
            $website = $this->storeManager->getWebsite($this->getRequest()->getParam('website', 0));

            foreach ($website->getStores() as $store) {
                $html .= $this->_getFieldHtml($element, $store);
            }
        } else {
            $html .= $htmlError;
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    /**
     * Get options values
     *
     * @return array
     */
    private function getValues()
    {
        $defaultValues = [
            [
                'value' => '',
                'label' => ''
            ]
        ];

        $values = [];

        $response = $this->client->sitesList();

        if ($response === false) {
            return $defaultValues;
        }

        if ($response->isSuccessful()) {
            $sites = $response['sites'];
        } else {
            return $defaultValues;
        }

        foreach ($sites as $site) {
            $values[] = [
                'label' => $site['name'],
                'value' => $site['code']
            ];
        }

        return $values;
    }

    /**
     * Get field html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $fieldset
     * @param \Magento\Shipping\Model\Carrier\AbstractCarrier $shipping
     *
     * @return string
     */
    protected function _getFieldHtml($fieldset, $store)
    {
        $configData = $this->getConfigData();
        $path = 'retailcrm/' . $fieldset->getId() . '/' . $store->getCode();
        $data = isset($configData[$path]) ? $configData[$path] : [];
        $e = $this->_getDummyElement();

        $field = $fieldset->addField(
            $store->getCode(),
            'select',
            [
                'name' => 'groups[' . $fieldset->getId() . '][fields][' . $store->getCode() . '][value]',
                'label' => $store->getName(),
                'value' => isset($data) ? $data : '',
                'values' => $this->getValues(),
                'inherit' => isset($data['inherit']) ? $data['inherit'] : '',
                'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e)
            ]
        )->setRenderer(
            $this->_getFieldRenderer()
        );

        return $field->toHtml();
    }
}
