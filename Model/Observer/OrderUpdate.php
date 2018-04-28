<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderUpdate implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $config;
    private $registry;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\Registry $registry
     * @param ApiClient $api
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\Registry $registry,
        ApiClient $api
    ) {
        $this->config = $config;
        $this->registry = $registry;
        $this->api = $api;
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
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return;
        }

        $order = $observer->getEvent()->getOrder();

        if ($order) {
            $preparedOrder = [
                'externalId' => $order->getId(),
                'status' => $this->config->getValue('retailcrm/Status/' . $order->getStatus())
            ];

            if ($order->getBaseTotalDue() == 0) {
                if ($this->api->getVersion() == 'v4') {
                    $preparedOrder['paymentStatus'] = 'paid';
                } elseif ($this->api->getVersion() == 'v5') {
                    $payment = [
                        'externalId' => $order->getPayment()->getId(),
                        'status' => 'paid'
                    ];

                    $this->api->ordersPaymentsEdit($payment);
                }
            }

            \Retailcrm\Retailcrm\Helper\Data::filterRecursive($preparedOrder);
            $this->api->ordersEdit($preparedOrder);
        }
    }
}
