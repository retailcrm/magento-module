<?php

namespace Retailcrm\Retailcrm\Cron;

class Icml {
    protected $_logger;

    public function __construct() {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logger = new \Retailcrm\Retailcrm\Model\Logger\Logger($objectManager);
        $this->_logger = $logger;
    }
	
    public function execute()
    {
        $Icml = new \Retailcrm\Retailcrm\Model\Icml\Icml();
        $Icml->generate();
    }
}
