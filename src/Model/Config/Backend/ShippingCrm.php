<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class ShippingCrm extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Retailcrm\Retailcrm\Helper\Proxy
     */
    private $client;

    /**
     * @var \Retailcrm\Retailcrm\Model\Logger\Logger
     */
    private $logger;

    /**
     * Activation constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Retailcrm\Retailcrm\Helper\Proxy $client,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param string $value
     * @return Magently\Tutorial\Block\Adminhtml\Form\Field\Activation
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Parse to html.
     *
     * @return mixed
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $deliveryTypes = array();

            try {
                $response = $this->client->deliveryTypesList();
            } catch (\Exception $exception) {
                $this->logger->writeRow($exception->getMessage());
            }

            if (isset($response) && $response->isSuccessful()) {
                $deliveryTypes = $response['deliveryTypes'];
            }

            $this->addOption( 'null',  "not selected");
            if ($deliveryTypes) {
                foreach ($deliveryTypes as $deliveryType) {
                    $this->addOption($deliveryType['code'], $deliveryType['name']);
                }
            }
        }

        return parent::_toHtml();
    }
}
