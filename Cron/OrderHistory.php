<?php

namespace Retailcrm\Retailcrm\Cron;

class OrderHistory
{
    protected $_logger;

    public function __construct()
    {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $logger = new \Retailcrm\Retailcrm\Model\Logger\Logger($om);
        $this->_logger = $logger;
    }

    public function execute()
    {
        $history = new \Retailcrm\Retailcrm\Model\History\Exchange();
        $history->ordersHistory();

        $this->_logger->writeRow('Cron Works: OrderHistory');
    }
}
