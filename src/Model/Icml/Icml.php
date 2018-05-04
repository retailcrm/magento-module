<?php

namespace Retailcrm\Retailcrm\Model\Icml;

class Icml
{
    private $dd;
    private $eCategories;
    private $eOffers;
    private $shop;
    private $manager;
    private $category;
    private $product;
    private $storeManager;
    private $StockState;
    private $configurable;
    private $config;
    private $dirList;
    private $ddFactory;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $manager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $product,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\CatalogInventory\Api\StockStateInterface $StockState,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\DomDocument\DomDocumentFactory $ddFactory,
        \Magento\Framework\Filesystem\DirectoryList $dirList
    ) {
        $this->configurable = $configurable;
        $this->StockState = $StockState;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->category = $categoryCollectionFactory;
        $this->manager = $manager;
        $this->config = $config;
        $this->ddFactory = $ddFactory;
        $this->dirList = $dirList;
    }

    /**
     * Generate icml catelog
     *
     * @return void
     */
    public function generate()
    {
        $this->shop = $this->manager->getStore()->getId();

        $string = '<?xml version="1.0" encoding="UTF-8"?>
            <yml_catalog date="'.date('Y-m-d H:i:s').'">
                <shop>
                    <name>'.$this->manager->getStore()->getName().'</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = simplexml_load_string(
            $string,
            '\Magento\Framework\Simplexml\Element',
            LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        $this->dd = $this->ddFactory->create();
        $this->dd->preserveWhiteSpace = false;
        $this->dd->formatOutput = true;
        $this->dd->loadXML($xml->asXML());

        $this->eCategories = $this->dd
            ->getElementsByTagName('categories')->item(0);
        $this->eOffers = $this->dd
            ->getElementsByTagName('offers')->item(0);

        $this->addCategories();
        $this->addOffers();

        $this->dd->saveXML();
        $shopCode = $this->manager->getStore()->getCode();
        $this->dd->save($this->dirList->getRoot() . '/retailcrm_' . $shopCode . '.xml');
    }

    /**
     * Add product categories in icml catalog
     *
     * @return void
     */
    private function addCategories()
    {
        $collection = $this->category->create();
        $collection->addAttributeToSelect('*');

        foreach ($collection as $category) {
            if ($category->getId() > 1) {
                $e = $this->eCategories->appendChild(
                    $this->dd->createElement('category')
                );
                $e->appendChild($this->dd->createTextNode($category->getName()));
                $e->setAttribute('id', $category->getId());

                if ($category->getParentId() > 1) {
                    $e->setAttribute('parentId', $category->getParentId());
                }
            }
        }
    }

    /**
     * Write products in icml catalog
     *
     * @return void
     */
    private function addOffers()
    {
        $offers = $this->buildOffers();

        foreach ($offers as $offer) {
            $this->addOffer($offer);
        }
    }

    /**
     * Write product in icml catalog
     *
     * @param array $offer
     *
     * @return void
     */
    private function addOffer($offer)
    {
        $e = $this->eOffers->appendChild(
            $this->dd->createElement('offer')
        );

        $e->setAttribute('id', $offer['id']);
        $e->setAttribute('productId', $offer['productId']);

        if (!empty($offer['quantity'])) {
            $e->setAttribute('quantity', (int) $offer['quantity']);
        } else {
            $e->setAttribute('quantity', 0);
        }

        if (!empty($offer['categoryId'])) {
            foreach ($offer['categoryId'] as $categoryId) {
                $e->appendChild(
                    $this->dd->createElement('categoryId')
                )->appendChild(
                    $this->dd->createTextNode($categoryId)
                );
            }
        } else {
            $e->appendChild($this->dd->createElement('categoryId', 1));
        }

        $e->appendChild($this->dd->createElement('productActivity'))
        ->appendChild(
            $this->dd->createTextNode($offer['productActivity'])
        );

        $e->appendChild($this->dd->createElement('name'))
        ->appendChild(
            $this->dd->createTextNode($offer['name'])
        );

        $e->appendChild($this->dd->createElement('productName'))
        ->appendChild(
            $this->dd->createTextNode($offer['productName'])
        );

        $e->appendChild($this->dd->createElement('price'))
        ->appendChild(
            $this->dd->createTextNode($offer['initialPrice'])
        );

        if (!empty($offer['purchasePrice'])) {
            $e->appendChild($this->dd->createElement('purchasePrice'))
            ->appendChild(
                $this->dd->createTextNode($offer['purchasePrice'])
            );
        }

        if (!empty($offer['picture'])) {
            $e->appendChild($this->dd->createElement('picture'))
            ->appendChild(
                $this->dd->createTextNode($offer['picture'])
            );
        }

        if (!empty($offer['url'])) {
            $e->appendChild($this->dd->createElement('url'))
            ->appendChild(
                $this->dd->createTextNode($offer['url'])
            );
        }

        if (!empty($offer['vendor'])) {
            $e->appendChild($this->dd->createElement('vendor'))
            ->appendChild(
                $this->dd->createTextNode($offer['vendor'])
            );
        }

        if (!empty($offer['params'])) {
            foreach ($offer['params'] as $param) {
                $paramNode = $this->dd->createElement('param');
                $paramNode->setAttribute('name', $param['name']);
                $paramNode->setAttribute('code', $param['code']);
                $paramNode->appendChild(
                    $this->dd->createTextNode($param['value'])
                );
                $e->appendChild($paramNode);
            }
        }
    }

    /**
     * Build offers array
     *
     * @return array $offers
     */
    private function buildOffers()
    {
        $offers = [];

        $collection = $this->product->create();
        $collection->addFieldToFilter('visibility', 4);//catalog, search visible
        $collection->addAttributeToSelect('*');
        $picUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $customAdditionalAttributes = $this->config->getValue('retailcrm/Misc/attributes_to_export_into_icml');

        foreach ($collection as $product) {
            if ($product->getTypeId() == 'simple') {
                $offers[] = $this->buildOffer($product);
            }

            if ($product->getTypeId() == 'configurable') {
                $associated_products = $this->getAssociatedProducts($product);

                foreach ($associated_products as $associatedProduct) {
                    $offers[] = $this->buildOffer($product, $associatedProduct);
                }
            }
        }

        return $offers;
    }

    /**
     * Build offer array
     *
     * @param object $product
     * @param object $associatedProduct
     * @return array $offer
     */
    private function buildOffer($product, $associatedProduct = null)
    {
        $offer = [];

        $picUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        $offer['id'] = $associatedProduct === null ? $product->getId() : $associatedProduct->getId();
        $offer['productId'] = $product->getId();

        if ($associatedProduct === null) {
            $offer['productActivity'] = $product->isAvailable() ? 'Y' : 'N';
        } else {
            $offer['productActivity'] = $associatedProduct->isAvailable() ? 'Y' : 'N';
        }

        $offer['name'] = $associatedProduct === null ? $product->getName() : $associatedProduct->getName();
        $offer['productName'] = $product->getName();
        $offer['initialPrice'] = $associatedProduct === null
            ? $product->getFinalPrice()
            : $associatedProduct->getFinalPrice();
        $offer['url'] = $product->getProductUrl();

        if ($associatedProduct === null) {
            $offer['picture'] = $picUrl . 'catalog/product' . $product->getImage();
        } else {
            $offer['picture'] = $picUrl . 'catalog/product' . $associatedProduct->getImage();
        }

        $offer['quantity'] = $associatedProduct === null
            ? $this->getStockQuantity($product)
            : $this->getStockQuantity($associatedProduct);
        $offer['categoryId'] = $associatedProduct === null
            ? $product->getCategoryIds()
            : $associatedProduct->getCategoryIds();
        $offer['vendor'] = $associatedProduct === null
            ? $product->getAttributeText('manufacturer')
            : $associatedProduct->getAttributeText('manufacturer');

        $offer['params'] = $this->getOfferParams($product, $associatedProduct);

        return $offer;
    }

    /**
     * Get parameters offers
     *
     * @param object $product
     * @param object $associatedProduct
     * @return array $params
     */
    private function getOfferParams($product, $associatedProduct = null)
    {
        $params = [];

        if ($associatedProduct !== null) {
            if ($associatedProduct->getResource()->getAttribute('color')) {
                $colorAttribute = $associatedProduct->getResource()->getAttribute('color');
                $color = $colorAttribute->getSource()->getOptionText($associatedProduct->getColor());
            }

            if (isset($color)) {
                $params[] = [
                    'name' => 'Color',
                    'code' => 'color',
                    'value' => $color
                ];
            }

            if ($associatedProduct->getResource()->getAttribute('size')) {
                $sizeAttribute = $associatedProduct->getResource()->getAttribute('size');
                $size = $sizeAttribute->getSource()->getOptionText($associatedProduct->getSize());
            }

            if (isset($size)) {
                $params[] = [
                    'name' => 'Size',
                    'code' => 'size',
                    'value' => $size
                ];
            }
        }

        $article = $associatedProduct === null ? $product->getSku() : $associatedProduct->getSku();

        if (!empty($article)) {
            $params[] = [
                'name' => 'Article',
                'code' => 'article',
                'value' => $article
            ];
        }

        $weight = $associatedProduct === null ? $product->getWeight() : $associatedProduct->getWeight();

        if (!empty($weight)) {
            $params[] = [
                'name' => 'Weight',
                'code' => 'weight',
                'value' => $weight
            ];
        }

        return $params;
    }

    /**
     * Get associated products
     *
     * @param object $product
     *
     * @return object
     */
    private function getAssociatedProducts($product)
    {
        return $this->configurable
            ->getUsedProductCollection($product)
            ->addAttributeToSelect('*')
            ->addFilterByRequiredOptions();
    }

    /**
     * Get product stock quantity
     *
     * @param object $offer
     * @return int $quantity
     */
    private function getStockQuantity($offer)
    {
        $quantity = $this->StockState->getStockQty(
            $offer->getId(),
            $offer->getStore()->getWebsiteId()
        );

        return $quantity;
    }
}
