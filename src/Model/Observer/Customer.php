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

    public function __construct(
        \Magento\Framework\Registry $registry,
        Helper $helper,
        ApiClient $api
    ) {
        $this->api = $api;
        $this->helper = $helper;
        $this->registry = $registry;
        $this->customer = [];
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return false;
        }

        $data = $observer->getEvent()->getCustomer();

        $this->customer = [
            'externalId' => $data->getId(),
            'email' => $data->getEmail(),
            'firstName' => $data->getFirstname(),
            'patronymic' => $data->getMiddlename(),
            'lastName' => $data->getLastname(),
            'createdAt' => date('Y-m-d H:i:s', strtotime($data->getCreatedAt()))
        ];

        $response = $this->api->customersEdit($this->customer);

        if ($response === false) {
            return false;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            $this->api->setSite($this->helper->getSite($data->getStore()));
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
