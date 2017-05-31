<?php

namespace Retailcrm\Retailcrm\Controller\Adminhtml\System\Config;

class Button extends \Magento\Backend\App\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
        parent::__construct($context);
    }


    public function execute()
    {
        $order = new \Retailcrm\Retailcrm\Model\Order\OrderNumber();
        $order->ExportOrderNumber();
    }
}