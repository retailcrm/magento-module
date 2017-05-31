<?php
namespace Retailcrm\Retailcrm\Cron;

class Icml {
    protected $_logger;

    public function __construct() {
        $om = \Magento\Framework\App\ObjectManager::getInstance();
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$this->_logger = $logger;
    }
	
    public function execute() {
    	$Icml = new \Retailcrm\Retailcrm\Model\Icml\Icml();
    	$Icml->generate();
    	
        $this->_logger->addDebug('Cron Works: create icml');
    }

}
