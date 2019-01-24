<?php

namespace Retailcrm\Retailcrm\Model\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;

class InventoriesUpload
{
    private $productRepo;
    private $api;
    
    public function __construct(
        ProductRepositoryInterface $productRepo,
        \Retailcrm\Retailcrm\Helper\Proxy $api
    ) {
        $this->productRepo = $productRepo;
        $this->api = $api;
    }

    /**
     * {@inheritdoc}
     */
    public function uploadInventory()
    {
        if (!$this->api->isConfigured()) {
            return false;
        }

        $page = 1;

        do {
            $response = $this->api->storeInventories(array(), $page, 250);

            if ($response === false || !$response->isSuccessful()) {
                return false;
            }

            foreach ($response['offers'] as $offer) {
                if (isset($offer['externalId'])) {
                   $product = $this->productRepo->getById($offer['externalId']);
                   $product->setStockData(
                        ['qty' => $offer['quantity'],
                        'is_in_stock' => $offer['quantity'] > 0]
                    );
                   $product->save();
                }
            }

            $totalPageCount = $response['pagination']['totalPageCount'];
            $page++;
        } while ($page <= $totalPageCount);

        return true;
    }
}
