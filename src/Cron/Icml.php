<?php

namespace Retailcrm\Retailcrm\Cron;

class Icml
{
    private $logger;
    private $icml;

    public function __construct(
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Retailcrm\Retailcrm\Model\Icml\Icml $icml
    ) {
        $this->logger = $logger;
        $this->icml = $icml;
    }

    public function execute()
    {
        $this->icml->generate();
    }
}
