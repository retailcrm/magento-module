<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;
use Retailcrm\Retailcrm\Helper\Data as Helper;

class Customer implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $registry;
    private $customer;
    private $helper;
    private $serviceCustomer;

    public function __construct(
        \Magento\Framework\Registry $registry,
        Helper $helper,
        ApiClient $api,
        \Retailcrm\Retailcrm\Model\Service\Customer $serviceCustomer
    ) {
        $this->api = $api;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->serviceCustomer = $serviceCustomer;
        $this->customer = [];
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return false;
        }

        $customer = $observer->getEvent()->getCustomer();
        $this->customer = $this->serviceCustomer->process($customer);
        $this->api->setSite($this->helper->getSite($customer->getStore()));
        $response = $this->api->customersEdit($this->customer);

        if ($response === false) {
            return false;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            $this->api->customersCreate($this->customer);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getCustomer()
    {
        return $this->customer;
    }
}
