<?php

namespace Retailcrm\Retailcrm\Model\Service;

class ConfigManager implements \Retailcrm\Retailcrm\Api\ConfigManagerInterface
{
    private $config;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    ) {
        $this->config = $config;
    }

    public function getConfigValue($path)
    {
        return $this->config->getValue($path);
    }
}
