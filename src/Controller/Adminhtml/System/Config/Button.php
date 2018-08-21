<?php

namespace Retailcrm\Retailcrm\Controller\Adminhtml\System\Config;

class Button extends \Magento\Backend\App\Action
{
    private $order;
    private $jsonFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Retailcrm\Retailcrm\Model\Order\OrderNumber $order
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Retailcrm\Retailcrm\Model\Order\OrderNumber $order,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        $this->order = $order;
        $this->jsonFactory = $jsonFactory;

        parent::__construct($context);
    }

    /**
     * Upload selected orders
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $numbers =  $this->getRequest()->getParam('numbers');
        $result = $this->order->exportOrderNumber($numbers);
        $resultJson = $this->jsonFactory->create();

        return $resultJson->setData($result);
    }
}
