<?php

class Retailcrm_Retailcrm_Block_Adminhtml_System_Config_Backend_Api_Key extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if ($this->isValueChanged()) {
            $api_url = $this->getFieldsetDataValue('api_url');
            $api_key = $this->getValue();

            if (!$api_key) {
                Mage::throwException(Mage::helper('retailcrm')->__('Field API KEY could not be empty'));
            }

            $api_client = new Retailcrm_Retailcrm_Model_ApiClient(
                $api_url,
                $api_key
            );

            try {
                $response = $api_client->sitesList();
            } catch (Retailcrm_Retailcrm_Model_Exception_CurlException $curlException) {
                Mage::throwException(Mage::helper('retailcrm')->__($curlException->getMessage()));
            } catch (Retailcrm_Retailcrm_Model_Exception_InvalidJsonException $invalidJsonException) {
                Mage::throwException(Mage::helper('retailcrm')->__($invalidJsonException->getMessage()));
            } catch (\InvalidArgumentException $invalidArgumentException) {
                Mage::throwException(Mage::helper('retailcrm')->__($invalidArgumentException->getMessage()));
            }

            if (isset($response['errorMsg'])) {
                Mage::throwException(Mage::helper('retailcrm')->__($response['errorMsg']));
            }
        }

        return $this;
    }
}
