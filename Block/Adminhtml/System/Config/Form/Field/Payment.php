<?php
namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field\Base;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Payment extends \Magento\Config\Block\System\Config\Form\Field
{
	protected $_apiUrl;
	protected $_apiKey;
	protected $_systemStore;
	protected $_formFactory;
	protected $_logger;
    	
	public function __construct(
			\Magento\Framework\Data\FormFactory $formFactory,
			\Magento\Store\Model\System\Store $systemStore
			
			)
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$logger = $om->get('\Psr\Log\LoggerInterface');
		$this->_logger = $logger;
		
		$base = new Base;
		
		$this->_apiUrl = $base->_apiUrl;
		$this->_apiKey = $base->_apiKey;
		
		$this->_systemStore = $systemStore;
		$this->_formFactory = $formFactory;
		
	}
	
	public function render(AbstractElement $element)
	{
		$values = $element->getValues();
		$html = '';
		
		if((!empty($this->_apiUrl))&&(!empty($this->_apiKey))){
		
			$objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
			$paymentConfig = $objectManager->get('Magento\Payment\Model\Config');
			$activePaymentMethods = $paymentConfig->getActiveMethods();
			
			$client = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient($this->_apiUrl,$this->_apiKey);
		
			try {
				$response = $client->paymentTypesList();
				if ($response->isSuccessful()&&200 === $response->getStatusCode()) {
					$paymentTypes = $response['paymentTypes'];
				}
			} catch (Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
				$this->_logger->addDebug($e->getMessage());
			}
			
			$config = \Magento\Framework\App\ObjectManager::getInstance()->get(
					'Magento\Framework\App\Config\ScopeConfigInterface'
					);
				
			foreach (array_keys($activePaymentMethods) as $k=>$payment){
				$html .='<table id="' . $element->getId() . '_table">';
				$html .='<tr id="row_retailcrm_payment_'.$payment.'">';
				$html .='<td class="label">'.$payment.'</td>';
				$html .='<td>';
				$html .='<select id="1" name="groups[Payment][fields]['.$payment.'][value]">';
				
				$selected = $config->getValue('retailcrm/Payment/'.$payment);
				
				foreach ($paymentTypes as $k=>$value){
					
					if((!empty($selected))&&(($selected==$value['code']))){
						$select ='selected="selected"';
					}else{
						$select ='';
					}
					
					$html .='<option '.$select.' value="'.$value['code'].'"> '.$value['name'].'</option>';
				}			
				$html .='</select>';
				$html .='</td>';
				$html .='</tr>';
				$html .='</table>';
				
			}
			
			return $html;
		}else{
			$html = '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';
			
			return $html;
		}
	}
	
	
}