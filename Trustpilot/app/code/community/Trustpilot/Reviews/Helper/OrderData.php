<?php

class Trustpilot_Reviews_Helper_OrderData extends Mage_Core_Helper_Abstract
{
    private $_helper;
    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
    }

    public function getInvitation($order, $hook, $collect_product_data = Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA) 
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
        }
        return $invitation;
    }

    public function handleSingleResponse($response, $order)
    {
        try {
            $synced_orders = (int)$this->_helper->getConfig('past_orders');
            $failed_orders = json_decode($this->_helper->getConfig('failed_orders'));

            if ($response['code'] == 201) {
                $synced_orders = (int)($synced_orders + 1);
                $this->_helper->setConfig('past_orders', $synced_orders);
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                    $this->_helper->setConfig('failed_orders', json_encode($failed_orders));
                }
            } else {
                $failed_orders->{$order['referenceId']} = base64_encode('Automatic invitation sending failed');
                $this->_helper->setConfig('failed_orders', json_encode($failed_orders));
            }
        } catch (Exception $e) {
            $message = 'Unable to update past orders for ' . $order['referenceId'] . '. Error: ' . $e->getMessage();
            $this->_helper->log($message);
        }
    }

    public function tryGetEmail($order)
    {
        if ($this->isEmpty($order))
            return '';

        if (!($this->isEmpty($order->getCustomerEmail())))
            return $order->getCustomerEmail();

        else if (!($this->isEmpty($order->getShippingAddress()->getEmail())))
            return $order->getShippingAddress()->getEmail();

        else if (!($this->isEmpty($order->getBillingAddress()->getEmail())))
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
    
    public function getProducts($order)
    {
        $products = array();
        try {
            $settings = json_decode($this->_helper->getConfig('master_settings_field'));
            $skuSelector = $settings->skuSelector;
            $gtinSelector = $settings->gtinSelector;
            $mpnSelector = $settings->mpnSelector;
        
            $items = $order->getAllVisibleItems();
            foreach ($items as $i) {
                $product = Mage::getModel('catalog/product')->load($i->getProductId());
                $manufacturer = $this->_helper->loadSelector($product, 'manufacturer');
                $sku = $this->_helper->loadSelector($product, $skuSelector);
                $mpn = $this->_helper->loadSelector($product, $mpnSelector);
                $gtin = $this->_helper->loadSelector($product, $gtinSelector);
                array_push(
                    $products,
                    array(
                        'productUrl' => $product->getProductUrl(),
                        'name' => $product->getName(),
                        'brand' => $manufacturer ? $manufacturer : '',
                        'sku' => $sku ? $sku : '',
                        'mpn' => $mpn ? $mpn : '',
                        'gtin' => $gtin ? $gtin : '',
                        'imageUrl' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) 
                            . 'catalog/product' . $product->getImage()
                    )
                );
            }
        } catch (Exception $e) {
            $message = 'Unable to gather items for order ' . $order->getIncrementId() . '. Error: ' . $e->getMessage();
            $this->_helper->log($message);
        }

        return $products;
    }
}
