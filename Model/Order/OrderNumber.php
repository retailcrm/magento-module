<?php
namespace Retailcrm\Retailcrm\Model\Order;

use \Retailcrm\Retailcrm\Observer;

//use Psr\Log\LoggerInterface;

class OrderNumber extends \Retailcrm\Retailcrm\Model\Observer\OrderCreate
{
	protected $_orderRepository;
	protected $_searchCriteriaBuilder;
	protected $_config;
	protected $_filterBuilder;
	protected $_order;
	protected $_helper;
	protected $_api;
	
	public function __construct()
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$orderRepository = $om->get('Magento\Sales\Model\OrderRepository');
		$searchCriteriaBuilder = $om->get('Magento\Framework\Api\SearchCriteriaBuilder');
		$config = $om->get('Magento\Framework\App\Config\ScopeConfigInterface');
		$filterBuilder = $om->get('Magento\Framework\Api\FilterBuilder');
		$order = $om->get('\Magento\Sales\Api\Data\OrderInterface');
		$helper = $om->get('\Retailcrm\Retailcrm\Helper\Data');
		
		$this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_config = $config;
        $this->_filterBuilder = $filterBuilder;
        $this->_order = $order;
        $this->_helper = $helper;
        
        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        
        if(!empty($url) && !empty($key)) {
        	$this->_api = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient($url,$key);
        }
	}
	
	public function ExportOrderNumber()
	{
		$ordernumber = $this->_config->getValue('retailcrm/Load/number_order');
		$ordersId = explode(",", $ordernumber);
		$orders = array();
		
		foreach ($ordersId as $id) {
			$orders[] = $this->prepareOrder($id);
		}
		
		$chunked = array_chunk($orders, 50);
		unset($orders);
		
		foreach ($chunked as $chunk) {
			$this->_api->ordersUpload($chunk);
			time_nanosleep(0, 250000000);
		}
		
		unset($chunked);
		
		return true;
		
	}
	
	public function prepareOrder($id)
	{
		$magentoOrder = $this->_order->loadByIncrementId($id);
		$magentoOrderArr = $magentoOrder->getData();
		
		$items = array();
		$addressObj = $magentoOrder->getBillingAddress();
		 
		foreach ($magentoOrder->getAllItems() as $item) {
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
		
		$ship = $this->getShippingCode($magentoOrder->getShippingMethod());
		
		$preparedOrder = array(
				'site' => $magentoOrder->getStore()->getCode(),
				'externalId' => $magentoOrder->getRealOrderId(),
				'number' => $magentoOrder->getRealOrderId(),
				'createdAt' => date('Y-m-d H:i:s'),
				'lastName' => $magentoOrder->getCustomerLastname(),
				'firstName' => $magentoOrder->getCustomerFirstname(),
				'patronymic' => $magentoOrder->getCustomerMiddlename(),
				'email' => $magentoOrder->getCustomerEmail(),
				'phone' => $addressObj->getTelephone(),
				'paymentType' => $this->_config->getValue('retailcrm/Payment/'.$magentoOrder->getPayment()->getMethodInstance()->getCode()),
				'status' => $this->_config->getValue('retailcrm/Status/'.$magentoOrder->getStatus()),
				'discount' => abs($magentoOrder->getDiscountAmount()),
				'items' => $items,
				'delivery' => array(
						'code' => $this->_config->getValue('retailcrm/Shipping/'.$ship),
						'cost' => $magentoOrder->getShippingAmount(),
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
		
		if ($magentoOrder->getCustomerIsGuest() == 0) {
			$preparedOrder['customer']['externalId'] = $magentoOrder->getCustomerId();
		}
		
		$logger = new \Retailcrm\Retailcrm\Model\Logger\Logger();
		$logger->write($preparedOrder,'OrderNumber');
		 
		return $this->_helper->filterRecursive($preparedOrder);
		 
	}
	
}