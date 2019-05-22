<?php

class Trustpilot_Reviews_Helper_Products extends Mage_Core_Helper_Abstract
{
    private $_helper;
    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
    }

    public function checkSkus($skuSelector) {
        $page_id = 1;
        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect(array('name', $skuSelector))
            ->setPageSize(20);
        $lastPage = $productCollection->getLastPageNumber();
        $productsWithoutSku = array();
        while ($page_id <= $lastPage) {
            set_time_limit(30);
            $collection = $productCollection->setCurPage($page_id)->load();
            if (isset($collection)) {
                foreach ($collection as $product) {
                    $sku = $this->_helper->loadSelector($product, $skuSelector);
                    if (empty($sku)) {
                        $item = array();
                        $item['id'] = $product->getId();
                        $item['name'] = $product->getName();
                        $item['productAdminUrl'] = Mage::helper('adminhtml')->getUrl('adminhtml/catalog_product/edit', array('id' => $product->getId()));
                        $item['productFrontendUrl'] = $product->getProductUrl();
                        array_push($productsWithoutSku, $item);
                    }
                }
            }
            $collection->clear();
            $page_id = $page_id + 1;
        }
        return $productsWithoutSku;
    }
}
