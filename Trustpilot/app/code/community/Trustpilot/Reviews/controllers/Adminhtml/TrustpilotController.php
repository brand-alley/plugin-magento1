<?php

    class Trustpilot_Reviews_Adminhtml_TrustpilotController
        extends Mage_Adminhtml_Controller_Action
    {
        const CODE_TEMPLATE = 'trustpilot/system/config/adminmenu.phtml';

        private $_pastOrders;
        private $_helper;
        private $_updater;
        private $_integrationAppUrl;
        private $_pluginUrl;
        private $_pluginVersion;
        private $_products;

        protected function _construct()
        {
            parent::_construct();
            $this->_helper = Mage::helper('trustpilot/Data');
            $this->_products = Mage::helper('trustpilot/Products');
            $this->_pastOrders = Mage::helper('trustpilot/PastOrders');
            $this->_updater = Mage::helper('trustpilot/Updater');
            $this->_integrationAppUrl = Trustpilot_Reviews_Model_Config::TRUSTPILOT_INTEGRATION_APP_URL;
            $this->_pluginUrl = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PLUGIN_URL;
            $this->_pluginVersion = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PLUGIN_VERSION;
        }

        protected function _isAllowed()
        {
            return Mage::getSingleton('admin/session')->isAllowed('trustpilot');
        }

        public function indexAction()
        {
            $this->loadLayout()->_setActiveMenu('trustpilot/trustpilot');

            $block = $this->getLayout()->createBlock('adminhtml/template')
                ->setData('data', array(
                        'IntegrationAppUrl' => $this->getIntegrationAppUrl(),
                        'PageUrls' => $this->_helper->getPageUrls(),
                        'Settings' => $this->getSettings(),
                        'ProductIdentificationOptions' => $this->getProductIdentificationOptions(),
                        'PastOrdersInfo' => $this->getPastOrdersInfo(),
                        'CustomTrustboxes' => $this->getCustomTrustBoxes(),
                        'StartingUrl' => $this->getStartingUrl(),
                        'Sku' => $this->getSku(),
                        'ProductName' => $this->getProductName(),
                        'ConfigurationScopeTree' => $this->getConfigurationScopeTree(),
                        'PluginStatus' => $this->getPluginStatus()
                    )
                )
                ->setTemplate(static::CODE_TEMPLATE);

            $this->getLayout()->getBlock('content')->append($block);
            $this->renderLayout();
        }

        public function ajaxAction()
        {
            $post = $this->getRequest()->getPost();
            $websiteId = isset($post["website_id"]) ? $post["website_id"] : null;
            $storeId = isset($post["store_id"]) ? $post["store_id"] : null;
            switch($post["action"]) {
                case 'handle_save_changes':
                    if (array_key_exists('settings', $post)) {
                        $responseBody = $post["settings"];
                        $this->_helper->setConfig('master_settings_field', $responseBody, $websiteId, $storeId);
                        $this->getResponse()->setBody($responseBody);
                        break;
                    } else if (array_key_exists('pageUrls', $post)) {
                        $responseBody = $post["pageUrls"];
                        $this->_helper->setConfig('page_urls', $responseBody, $websiteId, $storeId);
                        $this->getResponse()->setBody($responseBody);
                        break;
                    } else if (array_key_exists('customTrustBoxes', $post)) {
                        $responseBody = $post["customTrustBoxes"];
                        $this->_helper->setConfig('custom_trustboxes', $responseBody, $websiteId, $storeId);
                        $this->getResponse()->setBody($responseBody);
                        break;
                    }
                    break;
                case 'handle_past_orders':
                    if (array_key_exists('sync', $post)) {
                        $this->_pastOrders->sync($post["sync"], $websiteId, $storeId);
                        $output = $this->_pastOrders->getPastOrdersInfo();
                        $output['basis'] = 'plugin';
                        $output['pastOrders']['showInitial'] = false;
                        $this->getResponse()->setBody(json_encode($output));
                        break;
                    } else if (array_key_exists('resync', $post)) {
                        $this->_pastOrders->resync($websiteId, $storeId);
                        $output = $this->_pastOrders->getPastOrdersInfo();
                        $output['basis'] = 'plugin';
                        $this->getResponse()->setBody(json_encode($output));
                        break;
                    } else if (array_key_exists('issynced', $post)) {
                        $output = $this->_pastOrders->getPastOrdersInfo($websiteId, $storeId);
                        $output['basis'] = 'plugin';
                        $this->getResponse()->setBody(json_encode($output));
                        break;
                    } else if (array_key_exists('showPastOrdersInitial', $post)) {
                        $this->_helper->setConfig("show_past_orders_initial", $post["showPastOrdersInitial"], $websiteId, $storeId);
                        $this->getResponse()->setBody('true');
                        break;
                    }
                    break;
                case 'get_signup_data':
                    $results = base64_encode(json_encode($this->_helper->getBusinessInformation($websiteId, $storeId)));
                    $this->getResponse()->setBody($results);
                    break;
                case 'update_trustpilot_plugin':
                    $plugins = array(
                        array(
                            'name' => 'trustpilot',
                            'path' => $this->_pluginUrl,
                        )
                    );
                    $this->_updater->trustpilotGetPlugins($plugins);
                    break;
                case 'reload_trustpilot_settings':
                    $info = new stdClass();
                    $info->pluginVersion = $this->_pluginVersion;
                    $info->basis = 'plugin';
                    $this->getResponse()->setBody(json_encode($info));
                    break;
                case 'check_product_skus':
                    $result = new stdClass();
                    $result->skuScannerResults = $this->_products->checkSkus($post["skuSelector"]);
                    $this->getResponse()->setBody(json_encode($result));
                    break;
            }
        }

        private function getConfigurationScopeTree() {
            return base64_encode(json_encode($this->_helper->getConfigurationScopeTree()));
        }

        private function getProductIdentificationOptions()
        {
           return $this->_helper->getProductIdentificationOptions();
        }

        private function getIntegrationAppUrl()
        {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
            $domainName = $protocol . $this->_integrationAppUrl;
            return $domainName;
        }

        public function getCustomTrustBoxes()
        {
            $customTrustboxes = $this->_helper->getConfig('custom_trustboxes');
            if ($customTrustboxes) {
                return $customTrustboxes;
            }
            return "{}";
        }

        public function getStartingUrl()
        {
            return $this->_helper->getPageUrl('trustpilot_trustbox_homepage');
        }

        private function getSettings() {
            return base64_encode($this->_helper->getConfig('master_settings_field'));
        }

        private function getSku() {
            try {
                $product = $this->_helper->getFirstProduct();
                if ($product) {
                    $skuSelector = json_decode($this->_helper->getConfig('master_settings_field'))->skuSelector;
                    if ($skuSelector == 'none') $skuSelector = 'sku';
                    $productId = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PRODUCT_ID_PREFIX . $product->getId();
                    return $this->_helper->loadSelector($product, $skuSelector) . ', ' . $productId;
                }
            } catch (Throwable $exception) {
                return '';
            } catch (Exception $exception) {
                return '';
            }
        }

        private function getProductName() {
            try {
                return $this->_helper->getFirstProduct()->getName();
            } catch (Throwable $exception) {
                return '';
            } catch (Exception $exception) {
                return '';
            }
        }

        public function getPastOrdersInfo() {
            $info = $this->_pastOrders->getPastOrdersInfo();
            $info['basis'] = 'plugin';
            return json_encode($info);
        }

        public function getPluginStatus() {
            return base64_encode($this->_helper->getConfig('plugin_status'));
        }
    }
