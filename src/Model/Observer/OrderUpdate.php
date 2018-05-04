<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderUpdate implements \Magento\Framework\Event\ObserverInterface
{
    private $api;
    private $config;
    private $registry;
    private $order;

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
        $this->order = [];
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
            $this->order = [
                'externalId' => $order->getId(),
                'status' => $this->config->getValue('retailcrm/Status/' . $order->getStatus())
            ];

            if ($order->getBaseTotalDue() == 0) {
                if ($this->api->getVersion() == 'v4') {
                    $this->order['paymentStatus'] = 'paid';
                } elseif ($this->api->getVersion() == 'v5') {
                    $payment = [
                        'externalId' => $order->getPayment()->getId(),
                        'status' => 'paid'
                    ];

                    $this->api->ordersPaymentsEdit($payment);
                }
            }

            \Retailcrm\Retailcrm\Helper\Data::filterRecursive($this->order);
            $this->api->ordersEdit($this->order);
        }
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }
}
