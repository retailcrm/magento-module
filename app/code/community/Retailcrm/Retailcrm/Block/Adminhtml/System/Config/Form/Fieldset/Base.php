<?php
class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Form_Fieldset_Base extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    protected $_fieldRenderer;
    protected $_values;
    protected $_apiKey;
    protected $_apiUrl;
    protected $_isCredentialCorrect;
    
    public function __construct()
    {
        parent::__construct();

        $this->_apiUrl = Mage::getStoreConfig('retailcrm/general/api_url');
        $this->_apiKey = Mage::getStoreConfig('retailcrm/general/api_key');
        $this->_isCredentialCorrect = false;
        if (!empty($this->_apiUrl) && !empty($this->_apiKey)) {
            if (false === stripos($this->_apiUrl, 'https://')) {
                $this->_apiUrl = str_replace("http://", "https://", $this->_apiUrl);
                Mage::getModel('core/config')->saveConfig('retailcrm/general/api_url', $this->_apiUrl);
            }
            
            $client = new Retailcrm_Retailcrm_Model_ApiClient(
                $this->_apiUrl,
                $this->_apiKey
            );
            
            try {
                $response = $client->sitesList();                
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $e) {
                Mage::log($e->getMessage()); 
            }
            
            if ($response->isSuccessful()) {
                $this->_isCredentialCorrect = true;
                
                if($response['success'] != 1) {
                    Mage::getModel('core/config')->saveConfig('retailcrm/general/api_url', '');
                }           
            }
        }
    }
}
