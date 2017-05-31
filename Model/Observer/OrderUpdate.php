<?php

namespace Retailcrm\Retailcrm\Model\Observer;
use Magento\Framework\Event\Observer;

class OrderUpdate implements \Magento\Framework\Event\ObserverInterface
{
	protected $_api;
	protected $_config;
	protected $_helper;
	protected $_logger;

	public function __construct()
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$helper = $om->get('\Retailcrm\Retailcrm\Helper\Data');
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$config = $om->get('\Magento\Framework\App\Config\ScopeConfigInterface');
		
		$this->_logger = $logger;
		$this->_helper = $helper;
		$this->_config = $config;
		
		$url = $config->getValue('retailcrm/general/api_url');
		$key = $config->getValue('retailcrm/general/api_key');
		
		if(!empty($url) && !empty($key)) {
			$this->_api = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient($url,$key);
		}
		
	}
	
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$order = $observer->getEvent()->getOrder();
				
		if(isset($order)){
		
			$preparedOrder = array(
					'externalId' => $order->getRealOrderId(),
					'status' => $this->_config->getValue('retailcrm/Status/'.$order->getStatus()),
			);
			
			if((float)$order->getBaseGrandTotal() == (float)$order->getTotalPaid()){
				$preparedOrder['paymentStatus'] = 'paid';
			}
			
			$this->_helper->filterRecursive($preparedOrder);
			$this->_api->ordersEdit($preparedOrder);
			
		}
		
		
	}

}