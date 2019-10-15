<?php

class Trustpilot_Reviews_Helper_TrustpilotPluginStatus
{
    const TRUSTPILOT_SUCCESSFUL_STATUS = 200;

    private $_helper;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
    }

    public function setPluginStatus($response, $websiteId, $storeId)
    {
        $data = json_encode(
            array(
                'pluginStatus' => $response['code'],
                'blockedDomains' => $response['data'] ?: array(),
            )
        );

        $this->_helper->setConfig('plugin_status', $data, $websiteId, $storeId);
    }

    public function checkPluginStatus($origin, $websiteId, $storeId)
    {
        $data = json_decode($this->_helper->getConfig('plugin_status', $websiteId, $storeId));

        if (in_array(parse_url($origin, PHP_URL_HOST), $data->blockedDomains)) {
            return $data->pluginStatus;
        }

        return self::TRUSTPILOT_SUCCESSFUL_STATUS;
    }
}
