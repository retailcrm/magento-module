<?php

namespace Retailcrm\Retailcrm\Model\Observer;
use Magento\Framework\Event\Observer;

class Customer implements \Magento\Framework\Event\ObserverInterface
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
		$data = $observer->getEvent()->getCustomer();
		
		$customer = array(
				'externalId' => $data->getId(),
				'email' => $data->getEmail(),
				'firstName' => $data->getFirstname(),
				'patronymic' => $data->getMiddlename(),
				'lastName' => $data->getLastname(),
				'createdAt' => date('Y-m-d H:i:s', strtotime($data->getCreatedAt()))
		);
		
		$response = $this->_api->customersEdit($customer);
		
		if ((404 === $response->getStatusCode()) &&($response['errorMsg']==='Not found')) {
			$this->_api->customersCreate($customer);
		}
		
		//$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		//$logger->write($customer,'Customer');
		
	}
	
	

}