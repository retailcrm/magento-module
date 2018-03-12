<?php

namespace Retailcrm\Retailcrm\Model\Icml;

class Icml
{
    protected $_dd;
    protected $_eCategories;
    protected $_eOffers;
    protected $_shop;
    protected $_manager;
    protected $_category;
    protected $_product;
    protected $_storeManager;
    protected $_StockState;
    protected $_configurable;
    protected $_config;
    
    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $manager = $objectManager->get('Magento\Store\Model\StoreManagerInterface'); 
        $categoryCollectionFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
        $product = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $StockState = $objectManager->get('\Magento\CatalogInventory\Api\StockStateInterface');
        $configurable = $objectManager->get('Magento\ConfigurableProduct\Model\Product\Type\Configurable');
        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

        $this->_configurable = $configurable;
        $this->_StockState = $StockState;
        $this->_storeManager = $storeManager;
        $this->_product = $product;
        $this->_category = $categoryCollectionFactory;
        $this->_manager = $manager;
        $this->_config = $config;
    }
    
    public function generate()
    {
        $this->_shop = $this->_manager->getStore()->getId();
        
        $string = '<?xml version="1.0" encoding="UTF-8"?>
            <yml_catalog date="'.date('Y-m-d H:i:s').'">
                <shop>
                    <name>'.$this->_manager->getStore()->getName().'</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = new \SimpleXMLElement(
            $string,
            LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );
		
        $this->_dd = new \DOMDocument();
        $this->_dd->preserveWhiteSpace = false;
        $this->_dd->formatOutput = true;
        $this->_dd->loadXML($xml->asXML());

        $this->_eCategories = $this->_dd->
            getElementsByTagName('categories')->item(0);
        $this->_eOffers = $this->_dd
            ->getElementsByTagName('offers')->item(0);
  
        $this->addCategories();
        $this->addOffers();

        $this->_dd->saveXML();
        $dirlist = new \Magento\Framework\Filesystem\DirectoryList('');
        $baseDir = $dirlist->getRoot();
        $shopCode = $this->_manager->getStore()->getCode();
        $this->_dd->save($baseDir . 'retailcrm_' . $shopCode . '.xml');  
    }

    private function addCategories()
    {
        $collection = $this->_category->create();
        $collection->addAttributeToSelect('*');

        foreach ($collection as $category) {
            if ($category->getId() > 1){
                $e = $this->_eCategories->appendChild(
                    $this->_dd->createElement('category')
                );
                $e->appendChild($this->_dd->createTextNode($category->getName()));
                $e->setAttribute('id', $category->getId());

                if ($category->getParentId() > 1) {
                    $e->setAttribute('parentId', $category->getParentId());
                }		
            }
        }   	
    }

    private function addOffers()
    {
        $offers = [];

        $collection = $this->_product->create();
        $collection->addFieldToFilter('visibility', 4);//catalog, search visible
        $collection->addAttributeToSelect('*');

        $picUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();

        $customAdditionalAttributes = [];
        $customAdditionalAttributes = $this->_config->getValue('retailcrm/Misc/attributes_to_export_into_icml');

        foreach ($collection as $product) {
            if ($product->getTypeId() == 'simple') {
                $offer['id'] = $product->getId();
                $offer['productId'] = $product->getId();
                $offer['productActivity'] = $product->isAvailable() ? 'Y' : 'N';
                $offer['name'] = $product->getName();
                $offer['productName'] = $product->getName();
                $offer['initialPrice'] = $product->getFinalPrice();
                $offer['url'] = $product->getProductUrl();
                $offer['picture'] = $picUrl.'catalog/product'.$product->getImage();
                $offer['quantity'] = $this->_StockState->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
                $offer['categoryId'] = $product->getCategoryIds();
                $offer['vendor'] = $product->getAttributeText('manufacturer');
                $offer['params'] = [];

                $article = $product->getSku();

                if(!empty($article)) {
                    $offer['params'][] = [
                        'name' => 'Article',
                        'code' => 'article',
                        'value' => $article
                    ];
                }

                $weight = $product->getWeight();

                if(!empty($weight)) {
                    $offer['params'][] = [
                        'name' => 'Weight',
                        'code' => 'weight',
                        'value' => $weight
                    ];
                }

                if(!empty($customAdditionalAttributes)) {    	
                    //var_dump($customAdditionalAttributes);
                }

                $offers[] = $offer;
            }

            if ($product->getTypeId() == 'configurable') {
                $associated_products = $this->_configurable
                    ->getUsedProductCollection($product)
                    ->addAttributeToSelect('*')
                    ->addFilterByRequiredOptions();

                foreach ($associated_products as $associatedProduct) {
                    $offer['id'] = $associatedProduct->getId();
                    $offer['productId'] = $product->getId();
                    $offer['productActivity'] = $associatedProduct->isAvailable() ? 'Y' : 'N';
                    $offer['name'] = $associatedProduct->getName();
                    $offer['productName'] = $product->getName();
                    $offer['initialPrice'] = $associatedProduct->getFinalPrice();
                    $offer['url'] = $product->getProductUrl();
                    $offer['picture'] = $picUrl.'catalog/product'.$associatedProduct->getImage();
                    $offer['quantity'] = $this->_StockState->getStockQty($associatedProduct->getId(), $associatedProduct->getStore()->getWebsiteId());
                    $offer['categoryId'] = $associatedProduct->getCategoryIds();
                    $offer['vendor'] = $associatedProduct->getAttributeText('manufacturer');
                    $offer['params'] = [];

                    $article = $associatedProduct->getSku();

                    if ($associatedProduct->getResource()->getAttribute('color')) {
                        $colorAttribute = $associatedProduct->getResource()->getAttribute('color');
                        $color = $colorAttribute->getSource()->getOptionText($associatedProduct->getColor());
                    }

                    if (isset($color)) {
                        $offer['params'][] = [
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
                        $offer['params'][] = [
                            'name' => 'Size',
                            'code' => 'size',
                            'value' => $size
                        ];
                    }

                    if (!empty($article)) {
                        $offer['params'][] = [
                            'name' => 'Article',
                            'code' => 'article',
                            'value' => $article
                        ];
                    }

                    $weight = $associatedProduct->getWeight();

                    if(!empty($weight)) {
                        $offer['params'][] = [
                            'name' => 'Weight',
                            'code' => 'weight',
                            'value' => $weight
                        ];
                    }

                    $offers[] = $offer;
                }
            }
        }

        foreach ($offers as $offer) {
            $e = $this->_eOffers->appendChild(
                $this->_dd->createElement('offer')
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
                        $this->_dd->createElement('categoryId')
                    )->appendChild(
                        $this->_dd->createTextNode($categoryId)
                    );
                }
            } else {
                $e->appendChild($this->_dd->createElement('categoryId', 1));
            }

            $e->appendChild($this->_dd->createElement('productActivity'))
            ->appendChild(
                $this->_dd->createTextNode($offer['productActivity'])
            );

            $e->appendChild($this->_dd->createElement('name'))
            ->appendChild(
                $this->_dd->createTextNode($offer['name'])
            );

            $e->appendChild($this->_dd->createElement('productName'))
            ->appendChild(
                $this->_dd->createTextNode($offer['productName'])
            );

            $e->appendChild($this->_dd->createElement('price'))
            ->appendChild(
                $this->_dd->createTextNode($offer['initialPrice'])
            );

            if (!empty($offer['purchasePrice'])) {
                $e->appendChild($this->_dd->createElement('purchasePrice'))
                ->appendChild(
                    $this->_dd->createTextNode($offer['purchasePrice'])
                );
            }

            if (!empty($offer['picture'])) {
                $e->appendChild($this->_dd->createElement('picture'))
                ->appendChild(
                    $this->_dd->createTextNode($offer['picture'])
                );
            }

            if (!empty($offer['url'])) {
                $e->appendChild($this->_dd->createElement('url'))
                ->appendChild(
                    $this->_dd->createTextNode($offer['url'])
                );
            }

            if (!empty($offer['vendor'])) {
                $e->appendChild($this->_dd->createElement('vendor'))
                ->appendChild(
                    $this->_dd->createTextNode($offer['vendor'])
                );
            }

            if(!empty($offer['params'])) {
                foreach($offer['params'] as $param) {
                    $paramNode = $this->_dd->createElement('param');
                    $paramNode->setAttribute('name', $param['name']);
                    $paramNode->setAttribute('code', $param['code']);
                    $paramNode->appendChild(
                        $this->_dd->createTextNode($param['value'])
                    );
                    $e->appendChild($paramNode);
                }
            }
        }	
    }   
}
