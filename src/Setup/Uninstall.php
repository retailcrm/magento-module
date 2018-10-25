<?php

namespace Retailcrm\Retailcrm\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class Uninstall implements \Magento\Framework\Setup\UninstallInterface
{
    private $apiClient;
    private $integrationModule;

    public function __construct(
        \Retailcrm\Retailcrm\Helper\Proxy  $apiClient,
        \Retailcrm\Retailcrm\Model\Service\IntegrationModule $integrationModule
    ) {
        $this->apiClient = $apiClient;
        $this->integrationModule = $integrationModule;
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->integrationModule->setApiVersion($this->apiClient->getVersion());
        $this->integrationModule->sendConfiguration($this->apiClient, false);
    }
}
