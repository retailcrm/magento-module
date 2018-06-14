<?php

namespace Retailcrm\Retailcrm\Cron;

class OrderHistory
{
    private $logger;
    private $history;

    public function __construct(
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Retailcrm\Retailcrm\Model\History\Exchange $history
    ) {
        $this->logger = $logger;
        $this->history = $history;
    }

    public function execute()
    {
        $this->history->ordersHistory();
        $this->logger->writeRow('Cron Works: OrderHistory');
    }
}
