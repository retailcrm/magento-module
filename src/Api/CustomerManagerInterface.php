<?php

namespace Retailcrm\Retailcrm\Api;

interface CustomerManagerInterface
{
    public function process(\Magento\Customer\Model\Customer $customer);
}
