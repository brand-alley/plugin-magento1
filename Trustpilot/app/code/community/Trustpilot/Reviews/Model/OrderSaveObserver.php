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
            $store = Mage::app()->getStore($storeId);
            $settings = json_decode($this->_helper->getConfig('master_settings_field', null, $storeId));
            if (isset($settings->general->key)) {
                $key = $settings->general->key;
                $data = $this->_orderData->getInvitation($order, 'sales_order_save_after', Trustpilot_Reviews_Model_Config::WITHOUT_PRODUCT_DATA, $store);

                if (in_array($orderStatus, $settings->general->mappedInvitationTrigger)) {
                    $response = $this->_apiClient->postInvitation($key, null, $storeId, $data);

                    if ($response['code'] == '202') {
                        $data = $this->_orderData->getInvitation($order, 'sales_order_save_after', Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA, $store);
                        $response = $this->_apiClient->postInvitation($key, null, $storeId, $data);
                    }
                    $this->_orderData->handleSingleResponse($response, $data);
                } else {
                    $data['payloadType'] = 'OrderStatusUpdate';
                    $this->_apiClient->postInvitation($key, null, $storeId, $data);
                }
                return;
            }
        } catch (\Throwable $e) {
            $this->_helper->log('Error on collecting the data and sending invitation', $e, 'execute');
            return;
        } catch (\Exception $e) {
            $this->_helper->log('Error on collecting the data and sending invitation', $e, 'execute');
            return;
        }
    }


}
