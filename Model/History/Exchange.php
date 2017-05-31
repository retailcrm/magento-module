<?php
namespace Retailcrm\Retailcrm\Model\History;

class Exchange
{
	protected $_api;
	protected $_config;
	protected $_helper;
	protected $_logger;
	protected $_resourceConfig;
	protected $_customerFactory;
	protected $_quote;
	protected $_customerRepository;
	protected $_product;
	protected $_shipconfig;
	protected $_quoteManagement;
	protected $_registry;
	protected $_cacheTypeList;
	protected $_order;
	protected $_orderManagement;
	//protected $_transaction; 
	//protected $_invoiceService;
	protected $_eventManager;
	
	
	public function __construct()
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$helper = $om->get('\Retailcrm\Retailcrm\Helper\Data');
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$config = $om->get('\Magento\Framework\App\Config\ScopeConfigInterface');
		$resourceConfig = $om->get('Magento\Config\Model\ResourceModel\Config');
		$customerFactory = $om->get('\Magento\Customer\Model\CustomerFactory');
		$quote = $om->get('\Magento\Quote\Model\QuoteFactory');
		$customerRepository = $om->get('\Magento\Customer\Api\CustomerRepositoryInterface');
		$product = $om->get('\Magento\Catalog\Model\Product');
		$shipconfig = $om->get('\Magento\Shipping\Model\Config');
		$quoteManagement = $om->get('\Magento\Quote\Model\QuoteManagement');
		$registry = $om->get('\Magento\Framework\Registry');
		$cacheTypeList = $om->get('\Magento\Framework\App\Cache\TypeListInterface');
		$order = $om->get('\Magento\Sales\Api\Data\OrderInterface');
		$orderManagement = $om->get('\Magento\Sales\Api\OrderManagementInterface');		
		//$invoiceService = $om->get('\Magento\Sales\Model\Service\InvoiceService');
		//$transaction = $om->get('\Magento\Framework\DB\Transaction');
		$eventManager = $om->get('\Magento\Framework\Event\Manager');
		 
		
		$this->_shipconfig = $shipconfig;
		$this->_logger = $logger;
		$this->_helper = $helper;
		$this->_config = $config;
		$this->_resourceConfig = $resourceConfig;
		$this->_customerFactory = $customerFactory;
		$this->_quote = $quote;
		$this->_customerRepository = $customerRepository;
		$this->_product = $product;
		$this->_quoteManagement = $quoteManagement;
		$this->_registry = $registry;
		$this->_cacheTypeList = $cacheTypeList;
		$this->_order = $order;
		$this->_orderManagement = $orderManagement;
		//$this->_transaction = $transaction;
		//$this->_invoiceService = $invoiceService;
		$this->_eventManager = $eventManager;
		
		$url = $config->getValue('retailcrm/general/api_url');
		$key = $config->getValue('retailcrm/general/api_key');
		
		if(!empty($url) && !empty($key)) {
			$this->_api = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient($url,$key);
		}
	}
	
	public function ordersHistory()
	{
		$historyFilter = array();
		$historiOrder = array();
		
		$historyStart = $this->_config->getValue('retailcrm/general/filter_history');
		if($historyStart && $historyStart > 0) {
			$historyFilter['sinceId'] = $historyStart;
		}
		
		while(true) {
			try {
				$response = $this->_api->ordersHistory($historyFilter);
				if ($response->isSuccessful()&&200 === $response->getStatusCode()) {
					$nowTime = $response->getGeneratedAt(); 
					
				} else {
					$this->_logger->addDebug(
							sprintf("Orders history error: [HTTP status %s] %s", $response->getStatusCode(), $response->getErrorMsg())
							);
		
					if (isset($response['errors'])) {
						$this->_logger->addDebug(implode(' :: ', $response['errors']));
					}
		
					return false;
				}
			} catch (\Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
				
				$this->_logger->addDebug($e->getMessage());
		
				return false;
			}
		
			$orderH = isset($response['history']) ? $response['history'] : array();
			if(count($orderH) == 0) {
				return true;
			}
		
			$historiOrder = array_merge($historiOrder, $orderH);
			$end = array_pop($response->history);
			$historyFilter['sinceId'] = $end['id'];
		
			if($response['pagination']['totalPageCount'] == 1) {
				
				$this->_resourceConfig->saveConfig('retailcrm/general/filter_history', $historyFilter['sinceId'], 'default', 0);
				$this->_cacheTypeList->cleanType('config');
				
				$orders = self::assemblyOrder($historiOrder);
				
				$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
				$logger->write($orders,'OrderHistory');
				
				$this->processOrders($orders, $nowTime);
		
				return true;
			}
		}//endwhile
		 
	}
	
	/**
	 * @param array $orders
	 */
	private function processOrders($orders, $time)
	{
		$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		$logger->write($orders,'processOrders');
		
		if(!empty($orders)) {
	
			foreach ($orders as $order) {
				if(!empty($order['externalId'])) {
					$this->doUpdate($order);
				} else {
					$this->doCreate($order);
				}
			}
	
			die();
		}
	}
	
	/**
	 * @param array $order
	 */
	private function doCreate($order)
	{
		$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		$logger->write($order,'doCreate');
		
		$payments = $this->_config->getValue('retailcrm/Payment');
		$shippings = $this->_config->getValue('retailcrm/Shipping');
		
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$manager = $om->get('Magento\Store\Model\StoreManagerInterface');
		$store = $manager->getStore();
		$websiteId = $manager->getStore()->getWebsiteId();
		
		$customer = $this->_customerFactory->create();
		$customer->setWebsiteId($websiteId);
		$customer->loadByEmail($order['email']);// load customet by email address
		
		if(!$customer->getEntityId()){
			//If not avilable then create this customer
			$customer->setWebsiteId($websiteId)
				->setStore($store)
				->setFirstname($order['firstName'])
				->setLastname($order['lastName'])
				->setEmail($order['email'])
				->setPassword($order['email']);
			try {
				$customer->save();
			} catch (Exception $e) {
				$this->_logger->addDebug($e->getMessage());
			}
		}
		
		//Create object of quote
		$quote = $this->_quote->create();
		
		//set store for which you create quote
		$quote->setStore($store); 
		
		// if you have allready buyer id then you can load customer directly
		$customer = $this->_customerRepository->getById($customer->getEntityId());
		$quote->setCurrency();
		$quote->assignCustomer($customer); //Assign quote to customer
		
		//add items in quote
		foreach($order['items'] as $item){
			$product = $this->_product->load($item['offer']['externalId']);
			$product->setPrice($item['initialPrice']);
			$quote->addProduct(
				$product,
				intval($item['quantity'])
			);
		}
		
		$products = array();
		foreach ($order['items'] as $item) {
			$products[$item['offer']['externalId']] = array('qty' => $item['quantity']);
		}
		
		$orderData = array(
			'currency_id' => 'USD',
			'email' => $order['email'],
			'shipping_address' =>array(
				'firstname' => $order['firstName'],
				'lastname' => $order['lastName'],
				'street' => $order['delivery']['address']['street'],
				'city' => $order['delivery']['address']['city'],
				'country_id' => $order['delivery']['address']['countryIso'],//US
				'region' => $order['delivery']['address']['region'],
				'postcode' => $order['delivery']['address']['index'],
				'telephone' => $order['phone'],
				'save_in_address_book' => 1
			),
			'items'=> $products
		);
		
		$shippings = array_flip(array_filter($shippings));
		$payments = array_flip(array_filter($payments));
		
		$ShippingMethods = $this->getAllShippingMethodsCode($shippings[$order['delivery']['code']]);
		
		//Set Address to quote
		$quote->getBillingAddress()->addData($orderData['shipping_address']);
		$quote->getShippingAddress()->addData($orderData['shipping_address']);
		
		$shippingAddress=$quote->getShippingAddress();
		$shippingAddress->setCollectShippingRates(true)
			->collectShippingRates()
			->setShippingMethod($ShippingMethods);
			
		$quote->setPaymentMethod($payments[$order['paymentType']]);
		$quote->setInventoryProcessed(false);

		$quote->save();
				
		// Set Sales Order Payment
		$quote->getPayment()->importData(['method' => $payments[$order['paymentType']]]);
		
		// Collect Totals & Save Quote
		$quote->collectTotals()->save();
		
		// Create Order From Quote
		$magentoOrder = $this->_quoteManagement->submit($quote);
		
		try {
			$increment_id = $magentoOrder->getRealOrderId();
		} catch (\Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
			$this->_logger->addDebug($e->getMessage());
		}
		
		
		
		try {
			$response = $this->_api->ordersFixExternalIds(
				array(
					array(
						'id' => $order['id'],
						'externalId' =>$increment_id
					)
				)
			);
			
			if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
				$this->_logger->addDebug(
						sprintf(
								"Orders fix error: [HTTP status %s] %s",
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
	
	}
	
	/**
	 * @param array $order
	 */
	private function doCreateUp($order)
	{
		$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		$logger->write($order,'doCreateUp');
		
		try {
			$response = $this->_api->ordersGet($order['id'], $by = 'id');
		
			if ($response->isSuccessful() && 200 === $response->getStatusCode()) {
				$order = $response->order;
			} else {
				$this->_logger->addDebug(
						sprintf(
								"Orders get error: [HTTP status %s] %s",
								$response->getStatusCode(),
								$response->getErrorMsg()
								)
						);
		
				if (isset($response['errors'])) {
					$this->_logger->addDebug(implode(' :: ', $response['errors']));
				}
			}
		} catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
			$this->_logger->addDebug($e->getMessage());
		}
		
		
		$payments = $this->_config->getValue('retailcrm/Payment');
		$shippings = $this->_config->getValue('retailcrm/Shipping');
		
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$manager = $om->get('Magento\Store\Model\StoreManagerInterface');
		$store = $manager->getStore();
		$websiteId = $manager->getStore()->getWebsiteId();
		
		$customer = $this->_customerFactory->create();
		$customer->setWebsiteId($websiteId);
		$customer->loadByEmail($order['email']);// load customet by email address
		
		if(!$customer->getEntityId()){
			//If not avilable then create this customer
			$customer->setWebsiteId($websiteId)
			->setStore($store)
			->setFirstname($order['firstName'])
			->setLastname($order['lastName'])
			->setEmail($order['email'])
			->setPassword($order['email']);
			try {
				$customer->save();
			} catch (Exception $e) {
				$this->_logger->addDebug($e->getMessage());
			}
		}
		
		//Create object of quote
		$quote = $this->_quote->create();
		
		//set store for which you create quote
		$quote->setStore($store);
		
		// if you have allready buyer id then you can load customer directly
		$customer = $this->_customerRepository->getById($customer->getEntityId());
		$quote->setCurrency();
		$quote->assignCustomer($customer); //Assign quote to customer
		
		//add items in quote
		foreach($order['items'] as $item){
			$product = $this->_product->load($item['offer']['externalId']);
			$product->setPrice($item['initialPrice']);
			$quote->addProduct(
					$product,
					intval($item['quantity'])
					);
		}
		
		$products = array();
		foreach ($order['items'] as $item) {
			$products[$item['offer']['externalId']] = array('qty' => $item['quantity']);
		}
		
		$orderData = array(
				'currency_id' => 'USD',
				'email' => $order['email'],
				'shipping_address' =>array(
						'firstname' => $order['firstName'],
						'lastname' => $order['lastName'],
						'street' => $order['delivery']['address']['street'],
						'city' => $order['delivery']['address']['city'],
						'country_id' => $order['delivery']['address']['countryIso'],//US
						'region' => $order['delivery']['address']['region'],
						'postcode' => $order['delivery']['address']['index'],
						'telephone' => $order['phone'],
						'save_in_address_book' => 1
				),
				'items'=> $products
		);
		
		$shippings = array_flip(array_filter($shippings));
		$payments = array_flip(array_filter($payments));
		
		$ShippingMethods = $this->getAllShippingMethodsCode($shippings[$order['delivery']['code']]);
		
		//Set Address to quote
		$quote->getBillingAddress()->addData($orderData['shipping_address']);
		$quote->getShippingAddress()->addData($orderData['shipping_address']);
		
		$shippingAddress=$quote->getShippingAddress();
		$shippingAddress->setCollectShippingRates(true)
		->collectShippingRates()
		->setShippingMethod($ShippingMethods);
			
		$quote->setPaymentMethod($payments[$order['paymentType']]);
		$quote->setInventoryProcessed(false);
		
		
		$originalId = $order['externalId'];
		$oldOrder = $this->_order->loadByIncrementId($originalId);
		$oldOrderArr = $oldOrder->getData();
		
		if(!empty($oldOrderArr['original_increment_id'])) {
			$originalId = $oldOrderArr['original_increment_id'];
		}
		
		$orderDataUp = array(
			'original_increment_id'     => $originalId,
			'relation_parent_id'        => $oldOrder->getId(),
			'relation_parent_real_id'   => $oldOrder->getIncrementId(),
			'edit_increment'            => $oldOrder->getEditIncrement()+1,
			'increment_id'              => $originalId.'-'.($oldOrder->getEditIncrement()+1)
		);
		
		print_r($orderDataUp);
		
        $quote->setReservedOrderId($orderDataUp['increment_id']);
		$quote->save();
		
		// Set Sales Order Payment
		$quote->getPayment()->importData(['method' => $payments[$order['paymentType']]]);
		
		// Collect Totals & Save Quote
		$quote->collectTotals()->save();
		
		// Create Order From Quote
		$magentoOrder = $this->_quoteManagement->submit($quote,$orderDataUp);
		
		try {
			$increment_id = $magentoOrder->getRealOrderId();			
			
		} catch (\Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
			$this->_logger->addDebug($e->getMessage());
		}
		
		try {
			$response = $this->_api->ordersFixExternalIds(
				array(
					array(
							'id' => $order['id'],
							'externalId' =>$increment_id
						)
				)
			);
				
			if (!$response->isSuccessful() || 200 !== $response->getStatusCode()) {
				$this->_logger->addDebug(
						sprintf(
								"Orders fix error: [HTTP status %s] %s",
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
		
		
		
		
	}
	
	/**
	 * @param array $order
	 */
	private function doUpdate($order)
	{
		$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		$logger->write($order,'doUpdate');
		
		$Status = $this->_config->getValue('retailcrm/Status');
		$Status = array_flip(array_filter($Status));
		
		$magentoOrder = $this->_order->loadByIncrementId($order['externalId']);
		$magentoOrderArr = $magentoOrder->getData();
		
		$logger->write($magentoOrderArr,'magentoOrderArr');
		$logger->write($Status,'status');
		
		if((!empty($order['order_edit']))&&($order['order_edit'] == 1)) {
			$this->doCreateUp($order);
		}
		
		if (!empty($order['status'])) {
			$change = $Status[$order['status']];
			
			if($change == 'canceled'){
				$this->_orderManagement->cancel($magentoOrderArr['entity_id']);
			}
			
			if($change == 'holded'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('holded');
				$order_status->save();
			}
			
			if(($change == 'complete')||($order['status']== 'complete')){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('complete');
				$order_status->save();
			}
			
			if($change == 'closed'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('closed');
				$order_status->save();
			}
			
			if($change == 'processing'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('processing');
				$order_status->save();
					
			}
			
			if($change == 'fraud'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('fraud');
				$order_status->save();
			}
			
			if($change == 'payment_review'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('payment_review');
				$order_status->save();
			}
			
			if($change == 'paypal_canceled_reversal'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('paypal_canceled_reversal');
				$order_status->save();
			}
			
			if($change == 'paypal_reversed'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('paypal_reversed');
				$order_status->save();
			}
		
			if($change == 'pending_payment'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('pending_payment');
				$order_status->save();
			}
			
			if($change == 'pending_paypal'){
				$om = \Magento\Framework\App\ObjectManager::getInstance();
				$order_status = $om->get('Magento\Sales\Model\Order')->load($magentoOrder->getId());
				$order_status->setStatus('pending_paypal');
				$order_status->save();
			}
			
			
			
			
			
		}
		
		
		
		
		
		
		
		
	}
	
	
	public static function assemblyOrder($orderHistory)
	{
		$orders = array();
		foreach ($orderHistory as $change) {
			$change['order'] = self::removeEmpty($change['order']);
			if(isset($change['order']['items'])) {
				$items = array();
				foreach($change['order']['items'] as $item) {
					if(isset($change['created'])) {
						$item['create'] = 1;
					}
	
					$items[$item['id']] = $item;
				}
	
				$change['order']['items'] = $items;
			}
			
			$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
			$logger->write($change,'retailcrmHistoryAssemblyOrder');
				
			
			if(isset($change['order']['contragent']['contragentType'])) {
				$change['order']['contragentType'] = self::newValue($change['order']['contragent']['contragentType']);
				unset($change['order']['contragent']);
			}
	
			if(isset($orders[$change['order']['id']])) {
				$orders[$change['order']['id']] = array_merge($orders[$change['order']['id']], $change['order']);
			}
			else {
				$orders[$change['order']['id']] = $change['order'];
			}
	
			if($change['field'] == 'manager_comment'){
				$orders[$change['order']['id']][$change['field']] = $change['newValue'];
			}
	
			if(($change['field'] != 'status')&&
					($change['field'] != 'country')&&
					($change['field'] != 'manager_comment')&&
					($change['field'] != 'order_product.status')&&
					($change['field'] != 'payment_status')&&
					($change['field'] != 'prepay_sum')
					) {
						$orders[$change['order']['id']]['order_edit'] = 1;
					}
					 
					if(isset($change['item'])) {
						if($orders[$change['order']['id']]['items'][$change['item']['id']]) {
							$orders[$change['order']['id']]['items'][$change['item']['id']] = array_merge($orders[$change['order']['id']]['items'][$change['item']['id']], $change['item']);
						}
	
						else{
							$orders[$change['order']['id']]['items'][$change['item']['id']] = $change['item'];
						}
	
						if(empty($change['oldValue']) && $change['field'] == 'order_product') {
							$orders[$change['order']['id']]['items'][$change['item']['id']]['create'] = 1;
							$orders[$change['order']['id']]['order_edit'] = 1;
							unset($orders[$change['order']['id']]['items'][$change['item']['id']]['delete']);
						}
	
						if(empty($change['newValue']) && $change['field'] == 'order_product') {
							$orders[$change['order']['id']]['items'][$change['item']['id']]['delete'] = 1;
							$orders[$change['order']['id']]['order_edit'] = 1;
						}
	
						if(!empty($change['newValue']) && $change['field'] == 'order_product.quantity') {
							$orders[$change['order']['id']]['order_edit'] = 1;
						}
						 
						if(!$orders[$change['order']['id']]['items'][$change['item']['id']]['create'] && $fields['item'][$change['field']]) {
							$orders[$change['order']['id']]['items'][$change['item']['id']][$fields['item'][$change['field']]] = $change['newValue'];
						}
					}
					else {
						if((isset($fields['delivery'][$change['field']]))&&($fields['delivery'][$change['field']] == 'service')) {
							$orders[$change['order']['id']]['delivery']['service']['code'] = self::newValue($change['newValue']);
						}
						elseif(isset($fields['delivery'][$change['field']])) {
							$orders[$change['order']['id']]['delivery'][$fields['delivery'][$change['field']]] = self::newValue($change['newValue']);
						}
						elseif(isset($fields['orderAddress'][$change['field']])) {
							$orders[$change['order']['id']]['delivery']['address'][$fields['orderAddress'][$change['field']]] = $change['newValue'];
						}
						elseif(isset($fields['integrationDelivery'][$change['field']])) {
							$orders[$change['order']['id']]['delivery']['service'][$fields['integrationDelivery'][$change['field']]] = self::newValue($change['newValue']);
						}
						elseif(isset($fields['customerContragent'][$change['field']])) {
							$orders[$change['order']['id']][$fields['customerContragent'][$change['field']]] = self::newValue($change['newValue']);
						}
						elseif(strripos($change['field'], 'custom_') !== false) {
							$orders[$change['order']['id']]['customFields'][str_replace('custom_', '', $change['field'])] = self::newValue($change['newValue']);
						}
						elseif(isset($fields['order'][$change['field']])) {
							$orders[$change['order']['id']][$fields['order'][$change['field']]] = self::newValue($change['newValue']);
						}
	
						if(isset($change['created'])) {
							$orders[$change['order']['id']]['create'] = 1;
						}
	
						if(isset($change['deleted'])) {
							$orders[$change['order']['id']]['deleted'] = 1;
						}
					}
		}
	
		return $orders;
	}
	
	public static function removeEmpty($inputArray)
	{
		$outputArray = array();
		if (!empty($inputArray)) {
			foreach ($inputArray as $key => $element) {
				if(!empty($element) || $element === 0 || $element === '0') {
					if (is_array($element)) {
						$element = self::removeEmpty($element);
					}
	
					$outputArray[$key] = $element;
				}
			}
		}
	
		return $outputArray;
	}
	
	public static function newValue($value)
	{
		if(isset($value['code'])) {
			return $value['code'];
		} else{
			return $value;
		}
	}
	
	public function getAllShippingMethodsCode($mcode)
	{
		$activeCarriers = $this->_shipconfig->getActiveCarriers();
		$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
		foreach($activeCarriers as $carrierCode => $carrierModel)
		{
			$options = array();
			if( $carrierMethods = $carrierModel->getAllowedMethods()) {
				foreach ($carrierMethods as $methodCode => $method) {
					$code= $carrierCode.'_'.$methodCode;
					if($mcode == $carrierCode){
						$methods[$mcode] = $code;
					}
				}
			}
		}
		
		return $methods[$mcode];
	}
	
}