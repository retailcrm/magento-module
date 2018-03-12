<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class Customer implements \Magento\Framework\Event\ObserverInterface
{
    protected $_api;
    protected $_config;
    protected $_helper;
    protected $_logger;
    protected $_objectManager;
    protected $registry;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry
    ) {
        $helper = $objectManager->get('\Retailcrm\Retailcrm\Helper\Data');
        $logger = $objectManager->get('\Retailcrm\Retailcrm\Model\Logger\Logger');

        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_config = $config;
        $this->_objectManager = $objectManager;
        $this->registry = $registry;

        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        $version = $config->getValue('retailcrm/general/api_version');

        if (!empty($url) && !empty($key)) {
            $this->_api = new ApiClient($url, $key, $version);
        }
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true) {
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

        $response = $this->_api->customersEdit($customer);

        if ($response === false) {
            return;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->_api->getErrorText('errorNotFound')) {
            $this->_api->customersCreate($customer);
        }
    }
}
