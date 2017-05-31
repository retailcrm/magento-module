<?php
namespace Retailcrm\Retailcrm\Cron;

class OrderHistory {
    protected $_logger;

    public function __construct() {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$this->_logger = $logger;
    }
	
    public function execute() {
        $history = new \Retailcrm\Retailcrm\Model\History\Exchange();
        $history->ordersHistory();
    	
        $this->_logger->addDebug('Cron Works: OrderHistory');
    }

}