<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderUpdate implements \Magento\Framework\Event\ObserverInterface
{
    protected $_api;
    protected $_config;
    protected $_helper;
    protected $_objectManager;
    protected $registry;

    /**
     * Constructor
     * 
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry
    ) {
        $this->_helper = $objectManager->get('\Retailcrm\Retailcrm\Helper\Data');
        $this->_objectManager = $objectManager;
        $this->_config = $config;
        $this->registry = $registry;

        $url = $config->getValue('retailcrm/general/api_url');
        $key = $config->getValue('retailcrm/general/api_key');
        $apiVersion = $config->getValue('retailcrm/general/api_version');

        if (!empty($url) && !empty($key)) {
            $this->_api = new ApiClient($url, $key, $apiVersion);
        }
    }

    /**
     * Execute update order in CRM
     * 
     * @param Observer $observer
     * 
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true) {
            return;
        }

        $order = $observer->getEvent()->getOrder();

        if ($order) {
            $preparedOrder = [
                'externalId' => $order->getId(),
                'status' => $this->_config->getValue('retailcrm/Status/' . $order->getStatus())
            ];

            if ($order->getBaseTotalDue() == 0) {
                if ($this->_api->getVersion() == 'v4') {
                    $preparedOrder['paymentStatus'] = 'paid';
                } elseif ($this->_api->getVersion() == 'v5') {
                    $payment = [
                        'externalId' => $order->getPayment()->getId(),
                        'status' => 'paid'
                    ];

                    $this->_api->ordersPaymentsEdit($payment);
                }
            }

            $this->_helper->filterRecursive($preparedOrder);
            $this->_api->ordersEdit($preparedOrder);
        }
    }
}
