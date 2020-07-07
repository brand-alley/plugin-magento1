<?php

class Trustpilot_Reviews_Helper_OrderData extends Mage_Core_Helper_Abstract
{
    private $_helper;
    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
    }

    public function getInvitation($order, $hook, $collect_product_data = Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA, $store = null)
    {
        $invitation = null;
        if (!is_null($order)) {
            $invitation = array();
            $invitation['recipientEmail'] = trim($this->tryGetEmail($order));
            $invitation['recipientName'] = $order->getCustomerName();
            $invitation['referenceId'] = $order->getIncrementId();
            $invitation['source'] = 'Magento-' . Mage::getVersion();
            $invitation['pluginVersion'] = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PLUGIN_VERSION;
            $invitation['hook'] = $hook;
            $invitation['orderStatusId'] = $order->getState();
            $invitation['orderStatusName'] = $order->getState();
            if ($collect_product_data == Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA) {
                $products = $this->getProducts($order);
                $invitation['products'] = $products;
                $invitation['productSkus'] = $this->getSkus($products);
            }
            $invitation['templateParams'] = $this->_helper->getIdsByStore($store);
        }
        return $invitation;
    }

    public function handleSingleResponse($response, $order)
    {
        try {
            $synced_orders = (int)$this->_helper->getConfig('past_orders');
            $failed_orders = json_decode($this->_helper->getConfig('failed_orders'));

            if ($response['code'] == 201) {
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                    $this->_helper->setConfig('failed_orders', json_encode($failed_orders));
                }
            } else {
                $failed_orders->{$order['referenceId']} = base64_encode('Automatic invitation sending failed');
                $this->_helper->setConfig('failed_orders', json_encode($failed_orders));
            }
        } catch (\Throwable $e) {
            $vars = array(
                'referenceId' => $order['referenceId'],
            );
            $this->_helper->log('Unable to update past orders', $e, 'handleSingleResponse', $vars);
        } catch (\Exception $e) {
            $vars = array(
                'referenceId' => $order['referenceId'],
            );
            $this->_helper->log('Unable to update past orders', $e, 'handleSingleResponse', $vars);
        }
    }

    public function tryGetEmail($order)
    {
        if ($this->isEmpty($order))
            return '';

        if (!($this->isEmpty($order->getCustomerEmail())))
            return $order->getCustomerEmail();

        else if ($order->getShippingAddress() && !($this->isEmpty($order->getShippingAddress()->getEmail())))
            return $order->getShippingAddress()->getEmail();

        else if ($order->getBillingAddress() && !($this->isEmpty($order->getBillingAddress()->getEmail())))
            return $order->getBillingAddress()->getEmail();

        else if (!($this->isEmpty($order->getCustomerId())))
            return Mage::getModel('customer/customer')->load($order->getCustomerId())->getEmail();

        else if (Mage::getSingleton('customer/session')->isLoggedIn())
            return Mage::getSingleton('customer/session')->getCustomer()->getEmail();

        return '';
    }

    public function isEmpty($var)
    {
        return empty($var);
    }

    public function getSkus($products)
    {
        $skus = array();
        foreach ($products as $product) {
            array_push($skus, $product['sku']);
        }

        return $skus;
    }
    
    private function stripAllTags($string, $remove_breaks = false)
    {
        if (gettype($string) != 'string') {
            return '';
        }
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }
        return trim($string);
    }

    public function getProducts($order)
    {
        $products = array();
        try {
            $settings = json_decode($this->_helper->getConfig('master_settings_field'));
            $skuSelector = $settings->skuSelector;
            $gtinSelector = $settings->gtinSelector;
            $mpnSelector = $settings->mpnSelector;
            $currency = $order->getOrderCurrencyCode();
            $items = $order->getAllVisibleItems();
            $productModel = Mage::getModel('catalog/product')->setStoreId($order->getStoreId());
            foreach ($items as $i) {
                $product = $productModel->load($i->getProductId());

                $childProducts = array();
                if ($i->getHasChildren()) {
                    $orderChildItems = $i->getChildrenItems();
                    foreach ($orderChildItems as $item) {
                        array_push($childProducts, $productModel->load($item->getProductId()));
                    }
                }
                $manufacturer = $this->_helper->loadSelector($product, 'manufacturer', $childProducts);
                $sku = $this->_helper->loadSelector($product, $skuSelector, $childProducts);
                $mpn = $this->_helper->loadSelector($product, $mpnSelector, $childProducts);
                $gtin = $this->_helper->loadSelector($product, $gtinSelector, $childProducts);
                $productId = $this->_helper->loadSelector($product, 'id', $childProducts);
                $productDescription = html_entity_decode($this->stripAllTags($product->getDescription(), true));

                array_push(
                    $products,
                    array(
                        'price' => $product->getFinalPrice() ?: 0,
                        'currency' => $currency,
                        'categories' => $this->getProductCategories($product, $childProducts),
                        'description' => $productDescription, 
                        'images' => $this->getAllImages($product, $childProducts),
                        'tags' => $this->getAllTags($product, $childProducts),
                        'meta' => array(
                            'title' => $product->getMetaTitle() ?: $product->getName() ?: '',
                            'keywords' => $product->getMetaKeyword() ?: $product->getName() ?: '',    
                            'description' => html_entity_decode($this->stripAllTags($product->getMetaDescription(), true)) ?: substr($productDescription, 0, 255) ?: '',
                        ),
                        'manufacturer' => $manufacturer ?: '',
                        'productUrl' => $product->getProductUrl() ?: '',
                        'name' => $product->getName() ?: '',
                        'brand' => $product->getBrand() ? $product->getBrand() : $manufacturer,
                        'productId' => $productId,
                        'sku' => $sku ? $sku : '',
                        'mpn' => $mpn ? $mpn : '',
                        'gtin' => $gtin ? $gtin : '',
                        'imageUrl' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)
                            . 'catalog/product' . $product->getImage()
                    )
                );
            }
        } catch (\Throwable $e) {
            $vars = array(
                'referenceId' => $order->getIncrementId(),
            );
            $this->_helper->log('Unable to gather items', $e, 'getProducts', $vars);
        } catch (\Exception $e) {
            $vars = array(
                'referenceId' => $order->getIncrementId(),
            );
            $this->_helper->log('Unable to gather items', $e, 'getProducts', $vars);
        }

        return $products;
    }

    function getProductCategories($product, $childProducts = null) {
        $categories = array();
        $categoryIds = array();

        if (!empty($childProducts)) {
            foreach ($childProducts as $childProduct) {
                $childCategoryIds = $childProduct->getCategoryIds();
                if (!empty($childCategoryIds)) {
                    $categoryIds = array_merge($categoryIds, $childCategoryIds);
                }
            }
        } else {
            $categoryIds = $product->getCategoryIds();
        }

        foreach ($categoryIds as $id) {
            $category = Mage::getModel('catalog/category')->load($id) ;
            array_push($categories, $category->getName());
        }

        return $categories;
    }

    function getAllImages($product, $childProducts = null) {
        $images = array();

        if (!empty($childProducts)) {
            foreach ($childProducts as $childProduct) {
                foreach ($childProduct->getMediaGalleryImages() as $image) {
                    array_push($images, $image->getUrl());
                }
            }
        }

        foreach ($product->getMediaGalleryImages() as $image) {
            array_push($images, $image->getUrl());
        }

        return $images;
    }

    function getAllTags($product, $childProducts = null) {
        $tagArray = $this->getTags($product);

        if (!empty($childProducts)) {
            foreach ($childProducts as $childProduct) {
                $tagArray = array_merge($tagArray, $this->getTags($childProduct));
            }
        }

        return $tagArray;
    }

    function getTags($product) {
        $tagArray = array();
        try {
            $model = Mage::getModel('tag/tag');
            $tagCollection= $model->getResourceCollection()
                    ->addPopularity()
                    ->addStatusFilter($model->getApprovedStatus())
                    ->addProductFilter($product->getId())
                    ->setFlag('relation', true)
                    ->addStoreFilter(Mage::app()->getStore()->getId())
                    ->setActiveFilter()
                    ->load();
            $tags=$tagCollection->getItems();
            foreach ($tags as $tag) {
                array_push($tagArray, $tag->getName());
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Unable to extract tags', $e, 'getTags');
        } catch (\Exception $e) {
            $this->_helper->log('Unable to extract tags', $e, 'getTags');
        }
        return $tagArray;
    }
}
