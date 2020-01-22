<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class StatusCrm extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Retailcrm\Retailcrm\Helper\Proxy
     */
    private $client;
    /**
     * Activation constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Retailcrm\Retailcrm\Helper\Proxy $client,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->client = $client;
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

            $response = $this->client->statusesList();

            if ($response->isSuccessful()) {
                $statuses = $response['statuses'];
            }

            $this->addOption( 'null',  " ");
            foreach ($statuses as $status) {
                $this->addOption($status['code'], $status['name']);
            }
        }

        return parent::_toHtml();
    }
}
