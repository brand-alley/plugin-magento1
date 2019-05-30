<?php
class Trustpilot_Reviews_Model_ConfigObserver
{
    private $_helper;
    private $_apiClient;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_apiClient = Mage::helper('trustpilot/TrustpilotHttpClient');
    }

    public function execute($observer)
    {
        $key = $this->_helper->getKey();
        $settings = self::getSettings();
        $origin = Mage::getModel('core/store')->load($this->_helper->getStoreIdOrDefault())->getBaseUrl();
        $this->_apiClient->postSettings($key, $origin, $settings);
    }

    public function getSettings() 
    {
        $globalSettings = new \stdClass();
        $globalSettings->source         = 'Magento1';
        $globalSettings->pluginVersion  = '2.5.518';
        $globalSettings->version = 'Magento-' . Mage::getVersion();
        $globalSettings->stores = array();
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    $general = new \stdClass();
                    $general->key            = $this->_helper->getKey($store->getId());
                    $general->storeId        = $store->getId();
                    $general->storeCode      = $store->getCode();
                    $general->storeName      = $store->getName();
                    $general->storeTitle     = $store->getFrontendName();
                    $general->storeActive    = $store->getIsActive();
                    $general->storeHomeUrl   = base64_encode($store->getHomeUrl());
                    $general->currentUrl     = base64_encode($store->getCurrentUrl());
                    $general->websiteId      = $website->getId();
                    $general->websiteName    = $website->getName();
            
                    $trustbox = new \stdClass();
                    $trustbox->enabled  = trim($this->_helper->getTrustboxConfigValueByStore('trustpilot_trustbox', $store->getId()));
                    $trustbox->snippet  = base64_encode(trim($this->_helper->getTrustboxConfigValueByStore('trustpilot_code_snippet', $store->getId())));
                    $trustbox->position = trim($this->_helper->getTrustboxConfigValueByStore('trustpilot_position', $store->getId()));
                    $trustbox->xpath    = base64_encode(trim($this->_helper->getTrustboxConfigValueByStore('trustpilot_trustbox_xpath', $store->getId())));
                    $trustbox->page     = trim($this->_helper->getTrustboxConfigValueByStore('trustpilot_trustbox_page', $store->getId()));
            
                    $settings = new \stdClass();
                    $settings->general = $general;
                    $settings->trustbox = $trustbox;
                    array_push($globalSettings->stores, $settings);
                }
            }
        }
        return $globalSettings;
    }
}
