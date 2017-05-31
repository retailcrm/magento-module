<?php
namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field\Base;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Status extends \Magento\Config\Block\System\Config\Form\Field
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
			$manager = \Magento\Framework\App\ObjectManager::getInstance();
			$obj = $manager->create('Magento\Sales\Model\ResourceModel\Order\Status\Collection');
			$statuses = $obj->toOptionArray();
			
			$client = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient($this->_apiUrl,$this->_apiKey);
			
			try {
				$response = $client->statusesList();
				if ($response->isSuccessful()&&200 === $response->getStatusCode()) {
					$statusTypes = $response['statuses'];
				}
			} catch (Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
				$this->_logger->addDebug($e->getMessage());
			}
			
			$config = \Magento\Framework\App\ObjectManager::getInstance()->get(
					'Magento\Framework\App\Config\ScopeConfigInterface'
					);
			
			foreach ($statuses as $k => $status){
				$html .='<table id="' . $element->getId() . '_table">';
				$html .='<tr id="row_retailcrm_status_'.$status['label'].'">';
				$html .='<td class="label">'.$status['label'].'</td>';
				$html .='<td>';
				$html .='<select name="groups[Status][fields]['.$status['value'].'][value]">';
				
				$selected = $config->getValue('retailcrm/Status/'.$status['value']);
				
				$html .='<option value=""> Select status </option>';
				foreach ($statusTypes as $k=>$value){
					
					if((!empty($selected))&&(($selected==$value['name']))||(($selected==$value['code']))){
						$select ='selected="selected"';
					}else{
						$select ='';
					}
					
					$html .='<option '.$select.'value="'.$value['code'].'"> '.$value['name'].'</option>';
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