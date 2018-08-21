<?php

namespace Retailcrm\Retailcrm\Model\Order;

use Retailcrm\Retailcrm\Helper\Data as Helper;
use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class OrderNumber
{
    private $salesOrder;
    private $orderService;
    private $helper;
    private $api;

    public function __construct(
        Helper $helper,
        ApiClient $api,
        \Retailcrm\Retailcrm\Model\Service\Order $orderService,
        \Magento\Sales\Api\Data\OrderInterface $salesOrder
    ) {
        $this->api = $api;
        $this->helper = $helper;
        $this->salesOrder = $salesOrder;
        $this->orderService = $orderService;
    }

    /**
     * @param string $orderNumbers
     *
     * @return array
     */
    public function exportOrderNumber($orderNumbers)
    {
        $ordersId = explode(",", $orderNumbers);
        $orders = [];

        foreach ($ordersId as $id) {
            $magentoOrder = $this->salesOrder->load($id);
            $orders[$magentoOrder->getStore()->getId()][] = $this->orderService->process($magentoOrder);
        }

        foreach ($orders as $storeId => $ordersStore) {
            $chunked = array_chunk($ordersStore, 50);
            unset($ordersStore);

            foreach ($chunked as $chunk) {
                $this->api->setSite($this->helper->getSite($storeId));
                $response = $this->api->ordersUpload($chunk);

                /** @var \RetailCrm\Response\ApiResponse $response */
                if (!$response->isSuccessful()) {
                    return [
                        'success' => false,
                        'error' => $response->getErrorMsg()
                    ];
                }

                time_nanosleep(0, 250000000);
            }

            unset($chunked);
        }

        return ['success' => true];
    }
}
