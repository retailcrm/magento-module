<?php

namespace Retailcrm\Retailcrm\Model\Setting;

//use Psr\Log\LoggerInterface;

class Shipping implements \Magento\Framework\Option\ArrayInterface
{
	protected $_entityType;
	protected $_store;
	
	public function __construct(
			\Magento\Store\Model\Store $store,
			\Magento\Eav\Model\Entity\Type $entityType
			) {
				$this->_store = $store;
				$this->_entityType = $entityType;
	}
	
	public function toOptionArray()
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$activeShipping = $om->create('Magento\Shipping\Model\Config')->getActiveCarriers();
		
		$config = \Magento\Framework\App\ObjectManager::getInstance()->get(
				'Magento\Framework\App\Config\ScopeConfigInterface'
				);
		
		foreach($activeShipping as $carrierCode => $carrierModel)
		{
			$options = array();
			if( $carrierMethods = $carrierModel->getAllowedMethods() )
			{
				foreach ($carrierMethods as $methodCode => $method)
				{
					$code= $carrierCode.'_'.$methodCode;
					$options[]=array('value'=>$code,'label'=>$method);
				}
				$carrierTitle =$config->getValue('carriers/'.$carrierCode.'/title');
		
			}
			$methods[] = array('value'=>$options,'label'=>$carrierTitle);
		}
		
		return $methods;
		
	}
}