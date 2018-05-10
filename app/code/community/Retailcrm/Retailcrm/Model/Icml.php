<?php

class Retailcrm_Retailcrm_Model_Icml
{
    protected $_dd;
    protected $_eCategories;
    protected $_eOffers;
    protected $_shop;

    public function generate($shop)
    {
        $this->_shop = $shop;
        
        $string = '<?xml version="1.0" encoding="UTF-8"?>
            <yml_catalog date="'.date('Y-m-d H:i:s').'">
                <shop>
                    <name>'.$shop->getName().'</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = new SimpleXMLElement(
            $string,
            LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
        );

        $this->_dd = new DOMDocument();
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
        $baseDir = Mage::getBaseDir();
        $shopCode = $shop->getCode();
        $this->_dd->save($baseDir . DS . 'retailcrm_' . $shopCode . '.xml');
    }

    private function addCategories()
    {
        $category = Mage::getModel('catalog/category');
        $treeModel = $category->getTreeModel();
        $treeModel->load();

        $ids = $treeModel->getCollection()->getAllIds();
        $categories = array();

        if (!empty($ids))
        {
            foreach ($ids as $id)
            {
                if ($id > 1) {
                    $category = Mage::getModel('catalog/category');
                    $category->load($id);
                    $categoryData = array(
                        'id' => $category->getId(),
                        'name'=> $category->getName(),
                        'parentId' => $category->getParentId()
                    );
                    array_push($categories, $categoryData);
                }
            }
        }

        foreach($categories as $category) {
            $e = $this->_eCategories->appendChild(
                $this->_dd->createElement('category')
            );
            $e->appendChild($this->_dd->createTextNode($category['name']));
            $e->setAttribute('id', $category['id']);

            if ($category['parentId'] > 1) {
                $e->setAttribute('parentId', $category['parentId']);
            }
        }
    }

    private function addOffers()
    {
        $offers = array();
        $helper = Mage::helper('retailcrm');
        $picUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $baseUrl = Mage::getBaseUrl();
        
        $customAdditionalAttributes = Mage::getStoreConfig('retailcrm/attributes_to_export_into_icml');
        $customAdditionalAttributes = explode(',', $customAdditionalAttributes);

        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addUrlRewrite();
        
        $collection->addFieldToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $collection->addFieldToFilter('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        
        foreach ($collection as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            $offer = array();

            $offer['id'] = $product->getTypeId() != 'configurable' ? $product->getId() : null;
            $offer['productId'] = $product->getId();
            $offer['productActivity'] = $product->isAvailable() ? 'Y' : 'N';
            $offer['name'] = $product->getName();
            $offer['productName'] = $product->getName();
            $offer['initialPrice'] = (float) $product->getPrice();
            if($product->hasCost())
                $offer['purchasePrice'] = (float) $product->getCost();

            $offer['url'] = $product->getProductUrl();
            $offer['picture'] = $picUrl.'catalog/product'.$product->getImage();
            $offer['quantity'] = Mage::getModel('cataloginventory/stock_item')
                ->loadByProduct($product)->getQty();

            foreach ($product->getCategoryIds() as $category_id) {
                $offer['categoryId'][] = $category_id;
            }

            $offer['vendor'] = $product->getAttributeText('manufacturer');

            $offer['params'] = array();

            $article = $product->getSku();
            if(!empty($article)) {
                $offer['params'][] = array(
                    'name' => 'Article',
                    'code' => 'article',
                    'value' => $article
                );
            }

            $weight = $product->getWeight();
            if(!empty($weight)) {
                $offer['params'][] = array(
                    'name' => 'Weight',
                    'code' => 'weight',
                    'value' => $weight
                );
            }

            if(!empty($customAdditionalAttributes)) {
                foreach($customAdditionalAttributes as $customAdditionalAttribute) {
                    $alreadyExists = false;
                    foreach($offer['params'] as $param) {
                        if($param['code'] == $customAdditionalAttribute) {
                            $alreadyExists = true;
                            break;
                        }
                    }

                    if($alreadyExists) continue;

                    $attribute = $product->getAttributeText($customAdditionalAttribute);
                    if(!empty($attribute)) {
                        $offer['params'][] = array(
                            'name' => $customAdditionalAttribute,
                            'code' => $customAdditionalAttribute,
                            'value' => $attribute
                        );
                    }
                }
            }

            $offers[] = $offer;

            if($product->getTypeId() == 'configurable') {

                /** @var Mage_Catalog_Model_Product_Type_Configurable $product */
                $associatedProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);

                $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

                foreach($associatedProducts as $associatedProduct) {
                    $associatedProduct = Mage::getModel('catalog/product')->load($associatedProduct->getId());

                    $attributes = array();
                    foreach($productAttributeOptions as $productAttributeOption) {
                        $attributes[$productAttributeOption['label']] = $associatedProduct->getAttributeText($productAttributeOption['attribute_code']);
                    }

                    $attributesString = array();
                    foreach($attributes AS $attributeName=>$attributeValue) {
                        $attributesString[] = $attributeName.': '.$attributeValue;
                    }
                    
                    $attributesString = ' (' . implode(', ', $attributesString) . ')';

                    $offer = array();

                    $offer['id'] = $associatedProduct->getId();
                    $offer['productId'] = $product->getId();
                    $offer['productActivity'] = $associatedProduct->isAvailable() ? 'Y' : 'N';
                    $offer['name'] = $associatedProduct->getName().$attributesString;
                    $offer['productName'] = $product->getName();
                    $offer['initialPrice'] = (float) $associatedProduct->getFinalPrice();
                    if($associatedProduct->hasCost())
                        $offer['purchasePrice'] = (float) $associatedProduct->getCost();
                    $offer['url'] = $associatedProduct->getProductUrl();
                    $offer['picture'] = $picUrl.'catalog/product'.$associatedProduct->getImage();
                    $offer['quantity'] = Mage::getModel('cataloginventory/stock_item')
                        ->loadByProduct($associatedProduct)->getQty();

                    foreach ($associatedProduct->getCategoryIds() as $category_id) {
                        $offer['categoryId'][] = $category_id;
                    }

                    $offer['vendor'] = $associatedProduct->getAttributeText('manufacturer');

                    $offer['params'] = array();

                    $article = $associatedProduct->getSku();
                    if(!empty($article)) {
                        $offer['params'][] = array(
                            'name' => 'Article',
                            'code' => 'article',
                            'value' => $article
                        );
                    }

                    $weight = $associatedProduct->getWeight();
                    if(!empty($weight)) {
                        $offer['params'][] = array(
                            'name' => 'Weight',
                            'code' => 'weight',
                            'value' => $weight
                        );
                    }

                    if(!empty($attributes)) {
                        foreach($attributes as $attributeName => $attributeValue) {
                            $offer['params'][] = array(
                                'name' => $attributeName,
                                'code' => str_replace(' ', '_', strtolower($attributeName)),
                                'value' => $attributeValue
                            );
                        }
                    }

                    if(!empty($customAdditionalAttributes)) {
                        foreach($customAdditionalAttributes as $customAdditionalAttribute) {
                            $alreadyExists = false;
                            foreach($offer['params'] as $param) {
                                if($param['code'] == $customAdditionalAttribute) {
                                    $alreadyExists = true;
                                    break;
                                }
                            }

                            if($alreadyExists) continue;

                            $attribute = $associatedProduct->getAttributeText($customAdditionalAttribute);
                            if(!empty($attribute)) {
                                $offer['params'][] = array(
                                    'name' => $customAdditionalAttribute,
                                    'code' => $customAdditionalAttribute,
                                    'value' => $attribute
                                );
                            }
                        }
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

