<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Status extends \Magento\Config\Block\System\Config\Form\Fieldset
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

    private $objectFactory;
    private $statusCollection;
    private $client;

    public function __construct(
        \Magento\Backend\Block\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection,
        \Retailcrm\Retailcrm\Helper\Proxy $client,
        \Magento\Framework\DataObjectFactory $objectFactory,
        array $data = []
    ) {
        $this->statusCollection = $statusCollection;
        $this->client = $client;
        $this->objectFactory = $objectFactory;

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
            $this->_dummyElement = $this->objectFactory->create(['showInDefault' => 1, 'showInWebsite' => 1]);
        }

        return $this->_dummyElement;
    }

    public function render(AbstractElement $element)
    {
        $html = '';
        $htmlError = sprintf(
            '<div style="margin-left: 15px;"><b><i>%s</i></b></div>',
            __('Enter API of your URL and API key')
        );

        $html .= $this->_getHeaderHtml($element);

        if ($this->client->isConfigured()) {
            $statuses = $this->statusCollection->toOptionArray();

            foreach ($statuses as $code => $status) {
                $html .= $this->_getFieldHtml($element, $status);
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

        $response = $this->client->statusesList();

        if ($response === false) {
            return $defaultValues;
        }

        if ($response->isSuccessful()) {
            $statuses = $response['statuses'];
        } else {
            return $defaultValues;
        }

        foreach ($statuses as $status) {
            $values[] = [
                'label' => $status['name'],
                'value' => $status['code']
            ];
        }

        return $values;
    }

    /**
     * Get field html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $fieldset
     * @param array $status
     *
     * @return string
     */
    protected function _getFieldHtml($fieldset, $status)
    {
        $configData = $this->getConfigData();
        $path = 'retailcrm/' . $fieldset->getId() . '/' . $status['value'];

        $data = isset($configData[$path]) ? $configData[$path] : [];

        $e = $this->_getDummyElement();

        $field = $fieldset->addField(
            $status['value'],
            'select',
            [
                'name' => 'groups[' . $fieldset->getId() . '][fields][' . $status['value'] . '][value]',
                'label' => $status['label'],
                'value' => isset($data) ? $data : '',
                'values' => $this->getValues(),
                'inherit' => true,
                'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e)
            ]
        )->setRenderer(
            $this->_getFieldRenderer()
        );

        return $field->toHtml();
    }
}
