<?php

namespace Retailcrm\Retailcrm\Model\Service;

use Retailcrm\Retailcrm\Helper\Data as Helper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class IntegrationModule
{
    const LOGO = 'https://s3.eu-central-1.amazonaws.com/retailcrm-billing/images/5b846b1fef57e-magento.svg';
    const INTEGRATION_CODE = 'magento';
    const NAME = 'Magento 2';

    private $accountUrl = null;
    private $apiVersion = 'v5';
    private $configuration = [];
    private $resourceConfig;
    private $clientId;
    private $helper;

    /**
     * IntegrationModule constructor.
     *
     * @param \Magento\Config\Model\ResourceModel\Config $resourceConfig
     * @param \Retailcrm\Retailcrm\Helper\Data $helper
     */
    public function __construct(
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        Helper $helper
    ) {
        $this->resourceConfig = $resourceConfig;
        $this->helper = $helper;
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
        $this->clientId = $this->helper->getGeneralSettings('client_id_in_crm');

        if (!$this->clientId) {
            $this->clientId = uniqid();
        }

        if ($this->apiVersion == 'v4') {
            $this->configuration = [
                'name' => self::NAME,
                'code' => self::INTEGRATION_CODE . '-' . $this->clientId,
                'logo' => self::LOGO,
                'configurationUrl' => $this->accountUrl,
                'active' => $active
            ];
        } else {
            $this->configuration = [
                'clientId' => $this->clientId,
                'code' => self::INTEGRATION_CODE . '-' . $this->clientId,
                'integrationCode' => self::INTEGRATION_CODE,
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

        if ($response->isSuccessful() && $active == true) {

            $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeId = 0;

            $this->resourceConfig->saveConfig(
                Helper::XML_PATH_RETAILCRM . 'general/client_id_in_crm',
                $this->clientId,
                $scope,
                $scopeId
            );

            return true;
        }

        return false;
    }
}
