<?php

namespace Retailcrm\Retailcrm\Controller\Adminhtml\System\Config;

class Button extends \Magento\Backend\App\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    private $order;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Retailcrm\Retailcrm\Model\Order\OrderNumber $order
    ) {
        $this->order = $order;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->order->exportOrderNumber();
    }
}
