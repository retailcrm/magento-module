<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class Customer implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $registry;
    private $customer;

    public function __construct(
        \Magento\Framework\Registry $registry,
        ApiClient $api
    ) {
        $this->api = $api;
        $this->registry = $registry;
        $this->customer = [];
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return;
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
            return;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            $this->api->customersCreate($this->customer);
        }
    }

    /**
     * @return array
     */
    public function getCustomer()
    {
        return $this->customer;
    }
}
