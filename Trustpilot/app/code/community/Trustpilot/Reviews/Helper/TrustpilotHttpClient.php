<?php

 class Trustpilot_Reviews_Helper_TrustpilotHttpClient
 {
    private $_helper;
    private $_httpClient;
    private $_pluginStatus;
    private $_apiUrl;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_httpClient = Mage::helper('trustpilot/HttpClient');
        $this->_pluginStatus = Mage::helper('trustpilot/TrustpilotPluginStatus');
        $this->_apiUrl = Trustpilot_Reviews_Model_Config::TRUSTPILOT_API_URL;
    }

    public function post($url, $data, $origin, $websiteId, $storeId)
    {
        $httpRequest = 'POST';
        $response = $this->_httpClient->request(
            $url,
            $httpRequest,
            $origin,
            $data
        );

        if ($response['code'] > 250 && $response['code'] < 254) {
            $this->_pluginStatus->setPluginStatus($response, $websiteId, $storeId);
        }

        return $response;
    }

    public function buildUrl($key, $endpoint)
    {
        return $this->_apiUrl . $key . $endpoint;
    }

    public function checkStatusAndPost($url, $data, $websiteId, $storeId)
    {
        $origin = $this->_helper->getOrigin($websiteId, $storeId);
        $code = $this->_pluginStatus->checkPluginStatus($origin, $websiteId, $storeId);
        if ($code > 250 && $code < 254) {
            return array(
                'code' => $code,
            );
        }
        return $this->post($url, $data, $origin, $websiteId, $storeId);
    }

    public function postInvitation($key, $websiteId, $storeId, $data = array())
    {
        return $this->checkStatusAndPost($this->buildUrl($key, '/invitation'), $data, $websiteId, $storeId);
    }

    public function postBatchInvitations($key, $websiteId, $storeId, $data = array())
    {
        return $this->checkStatusAndPost($this->buildUrl($key, '/batchinvitations'), $data, $websiteId, $storeId);
    }

    public function postLog($data)
    {
        try {
            return $this->post($this->_apiUrl . 'log', $data, '*', null, null);
        } catch (Throwable $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
 }
