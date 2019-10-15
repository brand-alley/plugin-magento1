<?php

class Trustpilot_Reviews_Block_Success extends Mage_Checkout_Block_Onepage_Success
{
    protected $_helper;
    protected $_orderData;
    private $_pluginStatus;

    public function __construct()
    {
        $this->_helper = Mage::helper('trustpilot/Data');
        $this->_orderData = Mage::helper('trustpilot/OrderData');
        $this->_pluginStatus = Mage::helper('trustpilot/TrustpilotPluginStatus');
        parent::__construct();
    }

    public function getOrder()
    {
        try {
            $code = $this->_pluginStatus->checkPluginStatus($this->_helper->getOrigin());
            if ($code > 250 && $code < 254) {
                return 'undefined';
            }

            $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $order = Mage::getModel('sales/order')->load($orderId);
            $general_settings = json_decode($this->_helper->getConfig('master_settings_field'))->general;
            $store = Mage::app()->getStore();
            $data = $this->_orderData->getInvitation($order, 'magento1_success', Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA, $store);
            try {
                $data['totalCost'] = $order->getGrandTotal();
                $data['currency'] = $order->getOrderCurrencyCode();
            } catch (\Throwable $ex) {
            } catch (\Exception $ex) {}

            if (!in_array('trustpilotOrderConfirmed', $general_settings->mappedInvitationTrigger)) {
                $data['payloadType'] = 'OrderStatusUpdate';
            }

            return json_encode($data, JSON_HEX_APOS);
        } catch (\Throwable $e) {
            $this->_helper->log('Error on getting order', $e, 'getOrder');
            $error = array('message' => $e->getMessage());
            $data = array('error' => $error);
            return json_encode($data, JSON_HEX_APOS);
        } catch (\Exception $e) {
            $this->_helper->log('Error on getting order', $e, 'getOrder');
            $error = array('message' => $e->getMessage());
            $data = array('error' => $error);
            return json_encode($data, JSON_HEX_APOS);
        }
    }

    public function getLastRealOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    public function getEmail($order)
    {
        $email = $this->_orderData->tryGetEmail($order);

        if (!($this->_orderData->isEmpty($email)))
            return $email;

        $order = $this->getLastRealOrder();

        return $this->_orderData->tryGetEmail($order);
    }

}
