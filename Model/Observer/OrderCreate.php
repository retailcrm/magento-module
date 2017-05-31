<?php

namespace Retailcrm\Retailcrm\Model\Observer;
use Magento\Framework\Event\Observer;

class OrderCreate implements \Magento\Framework\Event\ObserverInterface
{
	protected $_api;
	protected $_objectManager;
	protected $_config;
	protected $_helper;
	protected $_logger;
	protected $_configurable;

	public function __construct(
			\Magento\Framework\ObjectManager\ObjectManager $ObjectManager,
			\Magento\Framework\App\Config\ScopeConfigInterface $config
		)
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$helper = $om->get('\Retailcrm\Retailcrm\Helper\Data');
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$configurable = $om->get('Magento\ConfigurableProduct\Model\Product\Type\Configurable');
		
		$this->_configurable = $configurable;
		$this->_logger = $logger;
		$this->_helper = $helper;
		$this->_objectManager = $ObjectManager;
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
	    $items = array();
	    $addressObj = $order->getBillingAddress();
	 	
	    
	    foreach ($order->getAllItems() as $item) {
	    	if ($item->getProductType() == "simple") {
	    	 	
	    		$price = $item->getPrice();
	    		
	    		if($price == 0){
	    			$om = \Magento\Framework\App\ObjectManager::getInstance();
	    			$omproduct = $om->get('Magento\Catalog\Model\ProductRepository')
	    				->getById($item->getProductId());
	    			$price = $omproduct->getPrice();
	    		}
	    		
	    		$product = array(
	    	 		'productId' => $item->getProductId(),
                    'productName' => $item->getName(),
                    'quantity' => $item->getQtyOrdered(),
                    'initialPrice' => $price,
	    			'offer'=>array(
	    				'externalId'=>$item->getProductId()
	    				)
	    	 	);
	    		
	    		unset($om);
	    		unset($omproduct);
	    		unset($price);
	    		
	    	 	$items[] = $product;
	    	}  
	    }
	    
	    $ship = $this->getShippingCode($order->getShippingMethod());
	    
	    $preparedOrder = array(
	    	'site' => $order->getStore()->getCode(),
	    	'externalId' => $order->getRealOrderId(),
	    	'number' => $order->getRealOrderId(),
	    	'createdAt' => date('Y-m-d H:i:s'),
	    	'lastName' => $order->getCustomerLastname(),
	    	'firstName' => $order->getCustomerFirstname(),
	    	'patronymic' => $order->getCustomerMiddlename(),
	    	'email' => $order->getCustomerEmail(),
	    	'phone' => $addressObj->getTelephone(),
	    	'paymentType' => $this->_config->getValue('retailcrm/Payment/'.$order->getPayment()->getMethodInstance()->getCode()),
	    	'status' => $this->_config->getValue('retailcrm/Status/'.$order->getStatus()),
	    	'discount' => abs($order->getDiscountAmount()),
	    	'items' => $items,
	    	'delivery' => array(
	    		'code' => $this->_config->getValue('retailcrm/Shipping/'.$ship),
	    		'cost' => $order->getShippingAmount(),
	    		'address' => array(
	    			'index' => $addressObj->getData('postcode'),
	    			'city' => $addressObj->getData('city'),
	    			'country' => $addressObj->getData('country_id'),
	    			'street' => $addressObj->getData('street'),
	    			'region' => $addressObj->getData('region'),
	    			'text' => trim(
	    				',',
	    				implode(
	    					',',
	   						array(
	    						$addressObj->getData('postcode'),
	    	     				$addressObj->getData('city'),
	    						$addressObj->getData('street'),
	    					)
	    				)
	    			)
	    		)
	    	)
	    );
	    
	    
	    if(trim($preparedOrder['delivery']['code']) == ''){
	    	unset($preparedOrder['delivery']['code']);
	    }
	    
	    if(trim($preparedOrder['paymentType']) == ''){
	    	unset($preparedOrder['paymentType']);
	    }
	    
	    if(trim($preparedOrder['status']) == ''){
	    	unset($preparedOrder['status']);
	    }
	    
	    if ($order->getCustomerIsGuest() == 0) {
	    	$preparedCustomer = array(
	    			'externalId' => $order->getCustomerId()
	    	);
	    
	    	if ($this->_api->customersCreate($preparedCustomer)) {
	    		$preparedOrder['customer']['externalId'] = $order->getCustomerId();
	    	}
	    }
	    
	    $this->_helper->filterRecursive($preparedOrder);
	    
	    $logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
	    $logger->write($preparedOrder,'CreateOrder');
	    
	    try {
	    	$response = $this->_api->ordersCreate($preparedOrder);
	    	if ($response->isSuccessful() && 201 === $response->getStatusCode()) {
	    		$this->_logger->addDebug($response->id);
	    		
	    	} else {
	    		$this->_logger->addDebug(
	    				sprintf(
	    						"Order create error: [HTTP status %s] %s",
	    						$response->getStatusCode(),
	    						$response->getErrorMsg()
	    						)
	    				);
	    		
	    		if (isset($response['errors'])) {
	    			$this->_logger->addDebug(implode(' :: ', $response['errors']));
	    		}
	    	}
	    } catch (\Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
	    	$this->_logger->addDebug($e->getMessage());
	    	 
	    }
	    
	     
	    return $this;
	}    

	protected function getShippingCode($string)
	{
		$split = array_values(explode('_', $string));
		$length = count($split);
		$prepare = array_slice($split, 0, $length/2);
	
		return implode('_', $prepare);
	}
}
