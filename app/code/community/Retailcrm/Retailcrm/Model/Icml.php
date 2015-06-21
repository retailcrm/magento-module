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
                    <name>'.Mage::app()->getStore($shop)->getName().'</name>
                    <categories/>
                    <offers/>
                </shop>
            </yml_catalog>
        ';

        $xml = new SimpleXMLElement(
            $string,
            LIBXML_NOENT |LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE
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
        $shopCode = Mage::app()->getStore($shop)->getCode();
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

        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addUrlRewrite();

        foreach ($collection as $product) {

            $offer = array();
            $offer['id'] = $product->getId();
            $offer['productId'] = $product->getId();
            $offer['productActivity'] = $product->isAvailable() ? 'Y' : 'N';
            $offer['name'] = $product->getName();
            $offer['productName'] = $product->getName();
            $offer['initialPrice'] = (float) $product->getPrice();

            $offer['url'] = $helper->rewrittenProductUrl(
                $product->getId(), $product->getCategoryId(), $this->_shop
            );

            $offer['picture'] = $picUrl.'catalog/product'.$product->getImage();

            $offer['quantity'] = Mage::getModel('cataloginventory/stock_item')
                ->loadByProduct($product)->getQty();

            foreach ($product->getCategoryIds() as $category_id) {
                $offer['categoryId'][] = $category_id;
            }

            $offer['vendor'] = $product->getAttributeText('manufacturer');

            $offer['article'] = $product->getSku();
            $offer['weight'] = $product->getWeight();

            $offers[] = $offer;

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
                        $this->_dd->createTextNode($offer['purchasePrice']
                    )
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
                        $this->_dd->createTextNode($offer['url']
                    )
                );
            }

            if (!empty($offer['vendor'])) {
                $e->appendChild($this->_dd->createElement('vendor'))
                    ->appendChild(
                        $this->_dd->createTextNode($offer['vendor']
                    )
                );
            }


            if (!empty($offer['article'] )) {
                $sku = $this->_dd->createElement('param');
                $sku->setAttribute('name', 'article');
                $sku->setAttribute('code', 'Article');
                $sku->appendChild(
                    $this->_dd->createTextNode($offer['article'])
                );
                $e->appendChild($sku);
            }

            if (!empty($offer['weight'] )) {
                $weight = $this->_dd->createElement('param');
                $weight->setAttribute('name', 'weight');
                $weight->setAttribute('code', 'Weight');
                $weight->appendChild(
                    $this->_dd->createTextNode($offer['weight'])
                );
                $e->appendChild($weight);
            }
        }
    }
}

