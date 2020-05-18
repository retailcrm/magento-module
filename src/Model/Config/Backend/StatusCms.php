<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class StatusCms extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\Collection
     */
    private $statusCollection;

    /**
     * @var \Retailcrm\Retailcrm\Model\Logger\Logger
     */
    private $logger;

    /**
     * StatusCms constructor.
     *
     * @param \Magento\Framework\View\Element\Context                    $context
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection
     * @param array                                                      $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->statusCollection = $statusCollection;
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
                $statuses = $this->statusCollection->toOptionArray();
            } catch (\Exception $exception) {
                $this->logger->writeRow($exception->getMessage());
            }

            $this->addOption( 'null',  "not selected");
            if ($statuses) {
                foreach ($statuses as $code => $status) {
                    $this->addOption( $status['value'],  $status['label']);
                }
            }
        }

        return parent::_toHtml();
    }
}
