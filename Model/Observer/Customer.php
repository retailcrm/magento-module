<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class Customer implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $registry;

    public function __construct(
        \Magento\Framework\Registry $registry,
        ApiClient $api
    ) {
        $this->api = $api;
        $this->registry = $registry;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return;
        }

        $data = $observer->getEvent()->getCustomer();

        $customer = [
            'externalId' => $data->getId(),
            'email' => $data->getEmail(),
            'firstName' => $data->getFirstname(),
            'patronymic' => $data->getMiddlename(),
            'lastName' => $data->getLastname(),
            'createdAt' => date('Y-m-d H:i:s', strtotime($data->getCreatedAt()))
        ];

        $response = $this->api->customersEdit($customer);

        if ($response === false) {
            return;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            $this->api->customersCreate($customer);
        }
    }
}
