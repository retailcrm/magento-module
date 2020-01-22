<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class StatusCms extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\Collection
     */
    private $statusCollection;

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
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->statusCollection = $statusCollection;
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
            $statuses = $this->statusCollection->toOptionArray();

            foreach ($statuses as $code => $status) {
                $this->addOption( $status['value'],  $status['label']);
            }
        }

        return parent::_toHtml();
    }
}
