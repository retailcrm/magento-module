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

        $xml = new SimpleXMLElement($string, LIBXML_NOENT |LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE);

        $this->_dd = new DOMDocument();
        $this->_dd->preserveWhiteSpace = false;
        $this->_dd->formatOutput = true;
        $this->_dd->loadXML($xml->asXML());

        $this->_eCategories = $this->_dd->getElementsByTagName('categories')->item(0);
        $this->_eOffers = $this->_dd->getElementsByTagName('offers')->item(0);

        $this->addCategories();
        $this->addOffers();

        $this->_dd->saveXML();
        $baseDir = Mage::getBaseDir();
        $this->_dd->save($baseDir . DS . 'retailcrm_' . Mage::app()->getStore($shop)->getCode() . '.xml');
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

        foreach($categories as $category) {
            $e = $this->_eCategories->appendChild($this->_dd->createElement('category'));
            $e->appendChild($this->_dd->createTextNode($category['name']));
            $e->setAttribute('id', $category['id']);

            if ($category['parentId'] > 0) {
                $e->setAttribute('parentId', $category['parentId']);
            }
        }
     }

    private function addOffers()
    {
        $offers = array();
        $helper = Mage::helper('retailcrm');

        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addUrlRewrite();

        foreach ($collection as $product) {

            $offer = array();
            $offer['id'] = $product->getId();
            $offer['productId'] = $product->getId();
            $offer['name'] = $product->getName();
            $offer['productName'] = $product->getName();
            $offer['initialPrice'] = (float) $product->getPrice();
            $offer['url'] = $helper->rewrittenProductUrl($product->getId(), $product->getCategoryId(), $this->_shop);
            $offer['picture'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product'.$product->getImage();
            $offer['quantity'] = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty();

            foreach ($product->getCategoryIds() as $category_id) {
                $offer['categoryId'][] = $category_id;
            }

            $offer['article'] = $product->getSku();
            $offer['weight'] = $product->getWeight();

            $offers[] = $offer;

        }

        foreach ($offers as $offer) {

            $e = $this->_eOffers->appendChild($this->_dd->createElement('offer'));
            $e->setAttribute('id', $offer['id']);
            $e->setAttribute('productId', $offer['productId']);

            if (isset($offer['quantity'] ) && $offer['quantity'] != '') {
                $e->setAttribute('quantity', (int)$offer['quantity']);
            }

            foreach ($offer['categoryId'] as $categoryId) {
                $e->appendChild($this->_dd->createElement('categoryId', $categoryId));
            }

            $e->appendChild($this->_dd->createElement('name'))->appendChild($this->_dd->createTextNode($offer['name']));
            $e->appendChild($this->_dd->createElement('productName'))->appendChild($this->_dd->createTextNode($offer['name']));
            $e->appendChild($this->_dd->createElement('price', $offer['initialPrice']));

            if (isset($offer['purchasePrice'] ) && $offer['purchasePrice'] != '') {
                $e->appendChild($this->_dd->createElement('purchasePrice'))->appendChild($this->_dd->createTextNode($offer['purchasePrice']));
            }

            if (isset($offer['vendor'] ) && $offer['vendor'] != '') {
                $e->appendChild($this->_dd->createElement('vendor'))->appendChild($this->_dd->createTextNode($offer['vendor']));
            }

            if (isset($offer['picture'] ) && $offer['picture'] != '') {
                $e->appendChild($this->_dd->createElement('picture', $offer['picture']));
            }

            if (isset($offer['url'] ) && $offer['url'] != '') {
                $e->appendChild($this->_dd->createElement('url'))->appendChild($this->_dd->createTextNode($offer['url']));
            }

            if (isset($offer['xmlId'] ) && $offer['xmlId'] != '') {
                $e->appendChild($this->_dd->createElement('xmlId'))->appendChild($this->_dd->createTextNode($offer['xmlId']));
            }

            if (isset($offer['article'] ) && $offer['article'] != '') {
                $sku = $this->_dd->createElement('param');
                $sku->setAttribute('name', 'article');
                $sku->appendChild($this->_dd->createTextNode($offer['article']));
                $e->appendChild($sku);
            }

            if (isset($offer['size'] ) && $offer['size'] != '') {
                $size = $this->_dd->createElement('param');
                $size->setAttribute('name', 'size');
                $size->appendChild($this->_dd->createTextNode($offer['size']));
                $e->appendChild($size);
            }

            if (isset($offer['color'] ) && $offer['color'] != '') {
                $color = $this->_dd->createElement('param');
                $color->setAttribute('name', 'color');
                $color->appendChild($this->_dd->createTextNode($offer['color']));
                $e->appendChild($color);
            }

            if (isset($offer['weight'] ) && $offer['weight'] != '') {
                $weight = $this->_dd->createElement('param');
                $weight->setAttribute('name', 'weight');
                $weight->appendChild($this->_dd->createTextNode($offer['weight']));
                $e->appendChild($weight);
            }
        }
    }
}

