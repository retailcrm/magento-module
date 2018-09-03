<?php

namespace Retailcrm\Retailcrm\Model\Service;

use Retailcrm\Retailcrm\Helper\Data as Helper;

class IntegrationModule
{
    const LOGO = 'https://s3.eu-central-1.amazonaws.com/retailcrm-billing/images/5b846b1fef57e-magento.svg';
    const CODE = 'magento';
    const NAME = 'Magento 2';

    private $accountUrl = null;
    private $resourceConfig;
    private $apiVersion = 'v5';
    private $configuration = [];

    public function __construct(
        \Magento\Config\Model\ResourceModel\Config $resourceConfig
    ) {
        $this->resourceConfig = $resourceConfig;
    }

    /**
     * @param $accountUrl
     */
    public function setAccountUrl($accountUrl)
    {
        $this->accountUrl = $accountUrl;
    }

    /**
     * @param $apiVersion
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param $active
     *
     * @return void
     */
    private function setConfiguration($active)
    {
        if ($this->apiVersion == 'v4') {
            $this->configuration = [
                'name' => self::NAME,
                'code' => self::CODE,
                'logo' => self::LOGO,
                'configurationUrl' => $this->accountUrl,
                'active' => $active
            ];
        } else {
            $clientId = hash('md5', date('Y-m-d H:i:s'));

            $this->configuration = [
                'clientId' => $clientId,
                'code' => self::CODE,
                'integrationCode' => self::CODE,
                'active' => $active,
                'name' => self::NAME,
                'logo' => self::LOGO,
                'accountUrl' => $this->accountUrl
            ];
        }
    }

    /**
     * @param \Retailcrm\Retailcrm\Helper\Proxy $apiClient
     * @param boolean $active
     *
     * @return boolean
     */
    public function sendConfiguration($apiClient, $active = true)
    {
        $this->setConfiguration($active);

        if ($this->apiVersion == 'v4') {
            $response = $apiClient->marketplaceSettingsEdit(Helper::filterRecursive($this->configuration));
        } else {
            $response = $apiClient->integrationModulesEdit(Helper::filterRecursive($this->configuration));
        }

        if (!$response) {
            return false;
        }

        if ($response->isSuccessful() && isset($clientId)) {
            $this->resourceConfig->saveConfig(Helper::XML_PATH_RETAILCRM . 'general/client_id_in_crm', $clientId);

            return true;
        }

        return false;
    }
}
