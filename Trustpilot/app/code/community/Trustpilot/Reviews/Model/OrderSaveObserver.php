<?php

class Trustpilot_Reviews_Model_OrderSaveObserver
{   
    private $_helper;

    public function __construct()
	{
        $this->_helper          = Mage::helper('trustpilot/Data');
        $this->_orderData       = Mage::helper('trustpilot/OrderData');
        $this->_apiClient       = Mage::helper('trustpilot/TrustpilotHttpClient');
	}
    
    public function execute(Varien_Event_Observer $observer) 
    { 
        try {
            $event = $observer->getEvent();
            $order = $event->getOrder();
            $orderStatus = $order->getState();
            $storeId = $order->getStoreId();
            $settings = json_decode($this->_helper->getConfig('master_settings_field', $storeId));
            if (isset($settings->general->key)) {
                $key = $settings->general->key;
                $data = $this->_orderData->getInvitation($order, 'sales_order_save_after', Trustpilot_Reviews_Model_Config::WITHOUT_PRODUCT_DATA);

                if (in_array($orderStatus, $settings->general->mappedInvitationTrigger)) {
                    $response = $this->_apiClient->postInvitation($key, $data);

                    if ($response['code'] == '202') {
                        $data = $this->_orderData->getInvitation($order, 'sales_order_save_after', Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA);
                        $response = $this->_apiClient->postInvitation($key, $data);
                    }
                    $this->_orderData->handleSingleResponse($response, $data);
                } else {
                    $data['payloadType'] = 'OrderStatusUpdate';
                    $this->_apiClient->postInvitation($key, $data);
                }
                return;
            }
        } catch (Exception $e) {
            $error = array('message' => $e->getMessage());
            $data = array('error' => $error);
            $this->_apiClient->postInvitation($key, $data);
            return;
        }     
    }


}