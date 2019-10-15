<?php

class Trustpilot_Reviews_Block_Trustbox extends Mage_Core_Block_Template
{
    protected $_helper;
    protected $_tbWidgetScriptUrl;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_tbWidgetScriptUrl = Trustpilot_Reviews_Model_Config::TRUSTPILOT_WIDGET_SCRIPT_URL;
        parent::__construct();
    }

    public function getWidgetScriptUrl()
    {
        return $this->_tbWidgetScriptUrl;
    }

    public function loadTrustboxes()
    {
        try {
            $settings = json_decode($this->_helper->getConfig('master_settings_field'))->trustbox;
            if ($settings->trustboxes) {
                $currentUrl = Mage::helper('core/url')->getCurrentUrl();
                $homePageId = Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE);
                $loadedTrustboxes = $this->loadPageTrustboxes($settings, $currentUrl);

                if (Mage::registry('current_product')) {
                    $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'product'), (array)$loadedTrustboxes);
                }
                else if (Mage::registry('current_category')) {
                    $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'category'), (array)$loadedTrustboxes);
                }
                if (Mage::getBlockSingleton('page/html_header')->getIsHomePage() ||
                        Mage::getSingleton('cms/page')->getIdentifier() == $homePageId) {
                    $loadedTrustboxes = array_merge((array)$this->loadPageTrustboxes($settings, 'landing'), (array)$loadedTrustboxes);
                }

                if (count($loadedTrustboxes) > 0) {
                    $settings->trustboxes = $loadedTrustboxes;
                    return json_encode($settings, JSON_HEX_APOS);
                }
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Error checking all the TrustBoxes', $e, 'loadTrustboxes');
        } catch (\Exception $e) {
            $this->_helper->log('Error checking all the TrustBoxes', $e, 'loadTrustboxes');
        }
        return '{"trustboxes":[]}';
    }

    private function loadPageTrustboxes($settings, $page)
    {
        $data = array();
        try {
            foreach ($settings->trustboxes as $trustbox) {
                if (($trustbox->page == $page || $this->checkCustomPage($trustbox->page, $page)) && $trustbox->enabled == 'enabled') {
                    $current_product = Mage::registry('current_product');
                    if ($current_product) {
                        $skuSelector = json_decode($this->_helper->getConfig('master_settings_field'))->skuSelector;
                        if ($skuSelector == 'none') {
                            $skuSelector = 'sku';
                        }
                        $skus = array();
                        $productSku = $this->_helper->loadSelector($current_product, $skuSelector);
                        if ($productSku) {
                            array_push($skus, $productSku);
                        }
                        array_push($skus, Trustpilot_Reviews_Model_Config::TRUSTPILOT_PRODUCT_ID_PREFIX . $current_product->getId());

                        if ($current_product->getTypeId() == 'configurable') {
                            $productTypeConfigurableModel = Mage::getModel('catalog/product_type_configurable')->setProduct($current_product);
                            $simpleProductCollection = $productTypeConfigurableModel->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
                            foreach ($simpleProductCollection as $product) {
                                $productSku = $this->_helper->loadSelector($product, $skuSelector);
                                if ($productSku) {
                                    array_push($skus, $productSku);
                                }
                                array_push($skus, Trustpilot_Reviews_Model_Config::TRUSTPILOT_PRODUCT_ID_PREFIX . $product->getId());
                            }
                        }

                        $trustbox->sku = implode(',', $skus);
                        $trustbox->name = $current_product->getName();
                    }
                    array_push($data, $trustbox);
                }
            }
        } catch (\Throwable $e) {
            $vars = array(
                'page' => isset($page) ? $page : null,
                'key' => isset($settings) ? $settings->key : null,
            );
            $this->_helper->log('Error on loading page TrustBoxes', $e, 'loadPageTrustboxes', $vars);
        } catch (\Exception $e) {
            $vars = array(
                'page' => isset($page) ? $page : null,
                'key' => isset($settings) ? $settings->key : null,
            );
            $this->_helper->log('Error on loading page TrustBoxes', $e, 'loadPageTrustboxes', $vars);
        }
        return $data;
    }

    private function checkCustomPage($tbPage, $page) {
        return (
            $tbPage == strtolower(base64_encode($page . '/')) ||
            $tbPage == strtolower(base64_encode($page)) ||
            $tbPage == strtolower(base64_encode(rtrim($page, '/')))
        );
    }
}
