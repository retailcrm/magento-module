<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

class Base extends \Magento\Framework\Model\AbstractModel
{
	public $_apiKey;
	public $_apiUrl;
	public $_isCredentialCorrect;
	protected $logger;
	protected $_cacheTypeList;
	protected $_resourceConfig;
	
	public function __construct() 
	{
		$om = \Magento\Framework\App\ObjectManager::getInstance();
        $config = $om->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $logger = $om->get('\Psr\Log\LoggerInterface');
        $cacheTypeList = $om->get('\Magento\Framework\App\Cache\TypeListInterface');
        $resourceConfig = $om->get('Magento\Config\Model\ResourceModel\Config');
        
        $this->_resourceConfig = $resourceConfig;
        $this->_cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
        $this->_apiUrl = $config->getValue('retailcrm/general/api_url');
        $this->_apiKey = $config->getValue('retailcrm/general/api_key');
        $this->_isCredentialCorrect = false;
        
        if (!empty($this->_apiUrl) && !empty($this->_apiKey)) {
        	if (false === stripos($this->_apiUrl, 'https://')) {
        		$this->_apiUrl = str_replace("http://", "https://", $this->_apiUrl);
        		$this->_resourceConfig->saveConfig('retailcrm/general/api_url', $this->_apiUrl, 'default', 0);
        		$this->_cacheTypeList->cleanType('config');
        	}
        
        	if (!$this->is_url($this->_apiUrl)){ 
        		echo 'URL not valid<br>';
        		echo 'Please check your Url and Reload page<br>';
        		
        		$this->_resourceConfig->saveConfig('retailcrm/general/api_url', '', 'default', 0);
        		$this->_cacheTypeList->cleanType('config');
        	}
        	
        	$client = new \Retailcrm\Retailcrm\Model\ApiClient\ApiClient(
        		$this->_apiUrl,
        		$this->_apiKey
        	);
        	 
        	try {
        		$response = $client->sitesList();
        		
        	} catch (\Retailcrm\Retailcrm\Model\ApiClient\Exception\CurlException $e) {
        		$this->_logger->addDebug($e->getMessage());
        	}
        	
        	if ($response->isSuccessful()) {
        		$this->_isCredentialCorrect = true;
        		if($response['success'] != 1) {
        			$this->_resourceConfig->saveConfig('retailcrm/general/api_url', '', 'default', 0);
        			$this->_cacheTypeList->cleanType('config');
        		}
        	}   	 
        }
        
    }
    
    
    public function is_url($url) {
    	return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }
    
}