<?php

namespace Retailcrm\Retailcrm\Cron;

class Icml
{
    private $logger;
    private $icml;
    private $storeManager;

    public function __construct(
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Retailcrm\Retailcrm\Model\Icml\Icml $icml,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->icml = $icml;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $websites = $this->storeManager->getWebsites();

        foreach ($websites as $website) {
            $this->icml->generate($website);
        }
    }
}
