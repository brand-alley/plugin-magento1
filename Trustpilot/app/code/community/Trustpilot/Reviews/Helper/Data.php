<?php

class Trustpilot_Reviews_Helper_Data extends Mage_Core_Helper_Abstract
{
    const TRUSTPILOT_SETTINGS = 'trustpilot/trustpilot_general_group/';

    public function getKey($websiteId = null, $storeId = null)
    {
        return trim(json_decode(self::getConfig('master_settings_field', $websiteId, $storeId))->general->key);
    }

    private function getDefaultConfigValues($key)
    {
        $config = array();
        $config['master_settings_field'] = json_encode(
            array(
                'general' => array(
                    'key' => '',
                    'invitationTrigger' => 'orderConfirmed',
                    'mappedInvitationTrigger' => array(),
                ),
                'trustbox' => array(
                    'trustboxes' => array(),
                ),
                'skuSelector' => 'none',
                'mpnSelector' => 'none',
                'gtinSelector' => 'none',
                'pastOrderStatuses' => array('processing', 'complete'),
            )
        );
        $config['sync_in_progress'] = 'false';
        $config['show_past_orders_initial'] = 'true';
        $config['past_orders'] = '0';
        $config['failed_orders'] = '{}';
        $config['custom_trustboxes'] = '{}';
        $config['plugin_status'] = json_encode(
            array(
                'pluginStatus' => 200,
                'blockedDomains' => array(),
            )
        );

        if (isset($config[$key])) {
            return $config[$key];
        }
        return false;
    }

    public function getConfig($config, $websiteId = null, $storeId = null)
    {
        $path = self::TRUSTPILOT_SETTINGS . $config;
        $setting = null;

        if ($storeId == null) {
            $storeId = self::getStoreId();
        }

        if ($websiteId == null) {
            $websiteId = self::getWebsiteId();
        }

        if ($storeId) {
            $setting =  Mage::app()->getStore($storeId)->getConfig($path);
        } else {
            if ($config == 'past_orders' || $config == 'failed_orders') {
                try {
                    $setting = $this->getWebsiteConfigWithSql($path, $websiteId);
                } catch (\Throwable $e) {
                } catch (\Exception $e) {}
            }
            if (!$setting) {
                $setting = Mage::app()->getWebsite($websiteId)->getConfig($path);
            }
        }

        return $setting ? $setting : $this->getDefaultConfigValues($config);
    }

    public function getWebsiteConfigWithSql($path, $websiteId)
    {
        $connection = Mage::getModel('core/resource')->getConnection('core_read');
        $table = $connection->getTableName('core_config_data');
        $sql = "SELECT * FROM `{$table}` WHERE `scope` = 'websites' AND `path` = '" . $path . "' AND `scope_id` = " . $websiteId;
        $values = $connection->fetchAll($sql);
        return count($values) ? $values[0]['value'] : false;
    }

    public function setConfig($config, $value, $websiteId = null, $storeId = null)
    {
        $path = self::TRUSTPILOT_SETTINGS . $config;

        if ($storeId == null) {
            $storeId = self::getStoreId();
        }
        if ($storeId) {
            Mage::getModel('core/config')->saveConfig($path, $value, 'stores', $storeId)->cleanCache();

        } else {
            if ($websiteId == null) {
                $websiteId = self::getWebsiteId();
            }
            Mage::getModel('core/config')->saveConfig($path, $value, 'websites', $websiteId)->cleanCache();
        }
    }

    public static function getStoreId()
    {
        // user at store
        $storeId = Mage::app()->getStore()->getStoreId();
        if ($storeId) {
            return $storeId;
        }
        // user at admin store level
        if (strlen($code = Mage::app()->getRequest()->getParam('store'))) {
            if (($storeId = Mage::getModel('core/store')->load($code)->getId())) {
                return $storeId;
            };
        }
        if (strlen($code = Mage::app()->getRequest()->getParam('website'))) {
            return false;
        }
        // user at admin default level
        return 0;
    }

    public static function getWebsiteId(){
        // user at admin website level
        if (strlen($code = Mage::app()->getRequest()->getParam('website'))) {
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            return $websiteId;
        }
        // get default website id
        $websites = Mage::getModel('core/website')->getCollection()->addFieldToFilter('is_default', 1);
        $website = $websites->getFirstItem();
        if ($website) {
            return $website->getId();
        }
        return 0;
    }

    public function getStoreIdOrDefault(){
        $storeId = $this->getStoreId();
        if ($storeId) {
            return $storeId;
        }
        return Mage::app()->getWebsite(self::getWebsiteId())->getDefaultStore()->getId();
    }

    public function loadSelector($product, $selector, $childProducts = null)
    {
        $attrValues = $this->loadOrderChildProducts($childProducts, $selector);
        if (!empty($attrValues)) {
            return implode(',', $attrValues);
        } else {
            return $this->loadAttributeValue($product, $selector);
        }
    }

    private function loadOrderChildProducts($childProducts, $selector)
    {
        if (!empty($childProducts)) {
            $attrValues = array();
            foreach ($childProducts as $product) {
                $attrValue = $this->loadAttributeValue($product, $selector);
                if (!empty($attrValue)) {
                    array_push($attrValues, $attrValue);
                }
            }
            return $attrValues;
        }
        return null;
    }

    private function loadAttributeValue($product, $selector)
    {
        try {
            if ($selector == 'id') {
                return (string) $product->getId();
            }
            if ($attribute = $product->getResource()->getAttribute($selector)) {
                $data = $product->getData($selector);
                $label = $attribute->getSource()->getOptionText($data);
                if (is_array($label)) {
                    $label = implode(', ', $label);
                }
                return $label ? $label : (string) $data;
            } else {
                return '';
            }
        } catch(\Exception $e) {
            return '';
        }
    }

    public function getFirstProduct($storeId = null) {
        $storeId = $storeId == null ? $this->getStoreIdOrDefault() : $storeId;
        return Mage::getModel('catalog/product')
            ->getCollection()
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('url_key', array('notnull' => true))
            ->addAttributeToFilter('visibility', array(2, 3, 4))
            ->addUrlRewrite()
            ->getFirstItem();
    }

    public function getConfigurationScopeTree() {
        $configurationScopeTree = array();
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    if ($store->getIsActive()) {
                        $names = array(
                            'site' => $website->getName(),
                            'store' => $store->getFrontendName(),
                            'view' => $store->getName()
                        );
                        $config = array(
                            'ids' => $this->getIdsByStore($store),
                            'names' => $names,
                            'domain' => parse_url($store->getBaseUrl(), PHP_URL_HOST),
                        );
                        array_push($configurationScopeTree,  $config);
                    }
                }
            }
        }
        return $configurationScopeTree;
    }

    public function getIdsByStore($store = null) {
        if ($store == null) {
            $storeId = self::getStoreId();
            $store = Mage::app()->getStore($storeId);
        }
        $ids = array();
        array_push($ids, $store->getWebsiteId());
        array_push($ids, $store->getStoreId());
        return $ids;
    }

    public function getPageUrls()
    {
        $pageUrls = new \stdClass();
        $pageUrls->landing = $this->getPageUrl('trustpilot_trustbox_homepage');
        $pageUrls->category = $this->getPageUrl('trustpilot_trustbox_category');
        $pageUrls->product = $this->getPageUrl('trustpilot_trustbox_product');

        $customPageUrls = json_decode($this->getConfig('page_urls'));
        $urls = (object) array_merge((array) $customPageUrls, (array) $pageUrls);
        return base64_encode(json_encode($urls));
    }

    public function getPageUrl($value)
    {
        try {
            $storeId = $this->getStoreIdOrDefault();
            switch ($value) {
                case 'trustpilot_trustbox_homepage':
                    return Mage::app()->getStore($storeId)->getUrl();
                case 'trustpilot_trustbox_category':
                    $attributes = Mage::getModel('catalog/category')->getAttributes();
                    if (isset($attributes['children_count'])) {
                        $urlPath = Mage::getModel('catalog/category')
                            ->getCollection()
                            ->addAttributeToSelect('*')
                            ->addAttributeToFilter('is_active', 1)
                            ->addAttributeToFilter('url_key', array('notnull' => true))
                            ->addAttributeToFilter('children_count', 0)
                            ->addUrlRewriteToResult()
                            ->getFirstItem()
                            ->getUrlPath();
                    } else if (isset($attributes['children'])) {
                        $urlPath = Mage::getModel('catalog/category')
                            ->getCollection()
                            ->addAttributeToSelect('*')
                            ->addAttributeToFilter('is_active', 1)
                            ->addAttributeToFilter('url_key', array('notnull' => true))
                            ->addAttributeToFilter('children', null)
                            ->addUrlRewriteToResult()
                            ->getFirstItem()
                            ->getUrlPath();
                    }
                    $url = Mage::getUrl($urlPath, array(
                        '_use_rewrite' => true,
                        '_secure' => true,
                        '_store' => $storeId,
                        '_store_to_url' => true
                    ));
                    return  $url;
                case 'trustpilot_trustbox_product':
                    return $this->getFirstProduct($storeId)
                        ->getUrlInStore(array(
                            '_store'=>$storeId
                        ));
                default:
                    return Mage::app()->getStore($storeId)->getUrl();
            }
        } catch (Throwable $e) {
            Mage::log('Unable to find URL for a page ' . $value . '. Error: ' . $e->getMessage());
            return Mage::getBaseUrl();
        } catch (Exception $e) {
            Mage::log('Unable to find URL for a page ' . $value . '. Error: ' . $e->getMessage());
            return Mage::getBaseUrl();
        }
    }

    public function log($message, $e, $method, $optional = array())
    {
        Mage::log($message);
        $log = array(
            'error' => $e->getMessage(),
            'platform' => 'Magento1',
            'version'  => Trustpilot_Reviews_Model_Config::TRUSTPILOT_PLUGIN_VERSION,
            'description'  => $message,
            'method'  => $method,
            'trace' => $e->getTraceAsString(),
            'variables' => $optional
        );
        Mage::helper('trustpilot/TrustpilotHttpClient')->postLog($log);
    }

    public function getProductIdentificationOptions()
    {
        $fields = array('none', 'sku', 'id');
        $optionalFields = array('upc', 'isbn', 'brand', 'manufacturer');
        $dynamicFields = array('mpn', 'gtin');
        $attrs = array_map(function ($t) { return $t; }, $this->getAttributes());

        foreach ($attrs as $attr) {
            foreach ($optionalFields as $field) {
                if ($attr == $field && !in_array($attr, $fields)) {
                    array_push($fields, $attr);
                    break;
                }
            }
            foreach ($dynamicFields as $field) {
                if (stripos($attr, $field) !== false && !in_array($attr, $fields)) {
                    array_push($fields, $attr);
                    break;
                }
            }
        }

        return json_encode($fields);
    }

    private function getAttributes()
    {
        $attr = array();
        $productAttrs = Mage::getResourceModel('catalog/product_attribute_collection');
        foreach ($productAttrs as $_productAttr) {
            array_push($attr, $_productAttr->getAttributeCode());
        }
        return $attr;
    }

    public function getBusinessInformation($websiteId, $storeId) {
        try {
            if ($storeId) {
                $store = Mage::app()->getStore($storeId);
                $useSecure = $store->getConfig('web/secure/use_in_frontend');
                $url = $store->getConfig('web/'. ($useSecure ? 'secure' : 'unsecure') .'/base_url');
                return array(
                    'website' => parse_url($url, PHP_URL_HOST),
                    'company' => $store->getConfig('general/store_information/name'),
                    'name' => $store->getConfig('trans_email/ident_general/name'),
                    'email' => $store->getConfig('trans_email/ident_general/email'),
                    'phone' => $store->getConfig('general/store_information/phone'),
                    'country' => $store->getConfig('general/store_information/merchant_country'),
                );
            } else if ($websiteId) {
                $website = Mage::app()->getWebsite($websiteId);
                $useSecure = $website->getConfig('web/secure/use_in_frontend');
                $url = $website->getConfig('web/'. ($useSecure ? 'secure' : 'unsecure') .'/base_url');
                return array(
                    'website' => parse_url($url, PHP_URL_HOST),
                    'company' => $website->getConfig('general/store_information/name'),
                    'name' => $website->getConfig('trans_email/ident_general/name'),
                    'email' => $website->getConfig('trans_email/ident_general/email'),
                    'phone' => $website->getConfig('general/store_information/phone'),
                    'country' => $website->getConfig('general/store_information/merchant_country'),
                );
            } else {
                $useSecure = Mage::getStoreConfig('web/secure/use_in_frontend');
                $url = Mage::getStoreConfig('web/'. ($useSecure ? 'secure' : 'unsecure') .'/base_url');
                return array(
                    'website' => parse_url($url, PHP_URL_HOST),
                    'company' => Mage::getStoreConfig('general/store_information/name'),
                    'name' => Mage::getStoreConfig('trans_email/ident_general/name'),
                    'email' => Mage::getStoreConfig('trans_email/ident_general/email'),
                    'phone' => Mage::getStoreConfig('general/store_information/phone'),
                    'country' => Mage::getStoreConfig('general/store_information/merchant_country'),
                );
            }
        } catch (\Throwable $e) {
            $this->log('Error on collecting signup data', $e, 'getBusinessInformation');
            return array();
        } catch (\Exception $e) {
            $this->log('Error on collecting signup data', $e, 'getBusinessInformation');
            return array();
        }
    }

    public function getOrigin($websiteId = null, $storeId = null)
    {
        if ($storeId) {
            $secure = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_IN_FRONT, $storeId);
            $param = $secure ? Mage_Core_Model_Url::XML_PATH_SECURE_URL : Mage_Core_Model_Url::XML_PATH_UNSECURE_URL;
            return Mage::getStoreConfig($param, $storeId);
        }
        $origin = null;
        if ($websiteId) {
            $origin = $this->getWebsiteConfigWithSql('web/unsecure/base_url', $websiteId);
        }
        return $origin != null ? $origin : Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }
}
