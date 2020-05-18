<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class StatusCrm extends \Magento\Framework\View\Element\Html\Select
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
            $statuses = array();

            try {
                $response = $this->client->statusesList();
            } catch (\Exception $exception) {
                $this->logger->writeRow($exception->getMessage());
            }

            if (isset($response) && $response->isSuccessful()) {
                $statuses = $response['statuses'];
            }

            $this->addOption( 'null',  "not selected");
            if ($statuses) {
                foreach ($statuses as $status) {
                    $this->addOption($status['code'], $status['name']);
                }
            }

        }

        return parent::_toHtml();
    }
}
