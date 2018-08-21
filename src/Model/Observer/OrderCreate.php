<?php

namespace Retailcrm\Retailcrm\Model\Observer;

use Magento\Framework\Event\Observer;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;
use Retailcrm\Retailcrm\Helper\Data as Helper;

class OrderCreate implements \Magento\Framework\Event\ObserverInterface
{
    protected $api;
    protected $logger;
    protected $helper;

    private $registry;
    private $order;
    private $serviceOrder;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Registry $registry
     * @param \Retailcrm\Retailcrm\Model\Logger\Logger $logger
     * @param \Retailcrm\Retailcrm\Model\Service\Order $serviceOrder
     * @param Helper $helper
     * @param ApiClient $api
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        \Retailcrm\Retailcrm\Model\Service\Order $serviceOrder,
        Helper $helper,
        ApiClient $api
    ) {
        $this->logger = $logger;
        $this->registry = $registry;
        $this->serviceOrder = $serviceOrder;
        $this->helper = $helper;
        $this->api = $api;
        $this->order = [];
    }

    /**
     * Execute send order in CRM
     *
     * @param Observer $observer
     *
     * @return mixed
     */
    public function execute(Observer $observer)
    {
        if ($this->registry->registry('RETAILCRM_HISTORY') === true
            || !$this->api->isConfigured()
        ) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();
        $this->api->setSite($this->helper->getSite($order->getStore()));

        if ($this->existsInCrm($order->getId()) === true) {
            return false;
        }

        $billingAddress = $order->getBillingAddress();
        $this->order = $this->serviceOrder->process($order);

        $this->setCustomer(
            $order,
            $billingAddress
        );

        Helper::filterRecursive($this->order);

        $this->logger->writeDump($this->order, 'CreateOrder');

        $this->api->ordersCreate($this->order);

        return $this;
    }

    /**
     * @param $order
     * @param $billingAddress
     */
    private function setCustomer($order, $billingAddress)
    {
        if ($order->getCustomerIsGuest() == 1) {
            $customer = $this->getCustomerByEmail($order->getCustomerEmail());

            if ($customer !== false) {
                $this->order['customer']['id'] = $customer['id'];
            }
        }

        if ($order->getCustomerIsGuest() == 0) {
            if ($this->existsInCrm($order->getCustomerId(), 'customersGet')) {
                $this->order['customer']['externalId'] = $order->getCustomerId();
            } else {
                $preparedCustomer = [
                    'externalId' => $order->getCustomerId(),
                    'firstName' => $order->getCustomerFirstname(),
                    'lastName' => $order->getCustomerLastname(),
                    'email' => $order->getCustomerEmail()
                ];

                if ($billingAddress->getTelephone()) {
                    $preparedCustomer['phones'][] = [
                        'number' => $billingAddress->getTelephone()
                    ];
                }

                if ($this->api->customersCreate($preparedCustomer)) {
                    $this->order['customer']['externalId'] = $order->getCustomerId();
                }
            }
        }
    }

    /**
     * Check exists order or customer in CRM
     *
     * @param int $id
     * @param string $method
     * @param string $by
     * @param string $site
     *
     * @return boolean
     */
    private function existsInCrm($id, $method = 'ordersGet', $by = 'externalId', $site = null)
    {
        $response = $this->api->{$method}($id, $by, $site);

        if ($response === false) {
            return false;
        }

        if (!$response->isSuccessful() && $response->errorMsg == $this->api->getErrorText('errorNotFound')) {
            return false;
        }

        return true;
    }

    /**
     * Get customer by email from CRM
     *
     * @param string $email
     *
     * @return mixed
     */
    private function getCustomerByEmail($email)
    {
        $response = $this->api->customersList(['email' => $email]);

        if ($response === false) {
            return false;
        }

        if ($response->isSuccessful() && isset($response['customers'])) {
            if (!empty($response['customers'])) {
                return reset($response['customers']);
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getOrder()
    {
        return $this->order;
    }
}
