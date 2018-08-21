<?php

namespace Retailcrm\Retailcrm\Api;

interface OrderManagerInterface
{
    public function process(\Magento\Sales\Model\Order $order);
}
