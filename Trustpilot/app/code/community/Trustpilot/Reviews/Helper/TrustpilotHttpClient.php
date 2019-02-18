<?php
 
 class Trustpilot_Reviews_Helper_TrustpilotHttpClient
 {
    private $_helper;
    private $_httpClient;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_httpClient = Mage::helper('trustpilot/HttpClient');
        $this->apiUrl = Trustpilot_Reviews_Model_Config::TRUSTPILOT_API_URL;
    }

    public function post($url, $data)
    {
        $origin = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $httpRequest = "POST";
        return $this->_httpClient->request(
            $url,
            $httpRequest,
            $origin,
            $data
        );
    }

    public function buildUrl($key, $endpoint)
    {
        return $this->apiUrl . $key . $endpoint;
    }

    public function postInvitation($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/invitation'), $data);
    }

    public function postBatchInvitations($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/batchinvitations'), $data);
    }

    public function postSettings($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/settings'), $data);
    }

    public function postLog($data)
    {
        try {
            return $this->post($this->apiUrl . 'log', $data);
        } catch (Exception $e) {
            return false;
        }
    }
 }