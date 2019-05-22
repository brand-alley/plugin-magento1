<?php

class Trustpilot_Reviews_Block_Success extends Mage_Checkout_Block_Onepage_Success
{
    protected $_helper;
    protected $_orderData;

    public function __construct()
    {
        $this->_helper           = Mage::helper('trustpilot/Data');
        $this->_orderData        = Mage::helper('trustpilot/OrderData');
        parent::__construct();
    }

    public function getOrder()
    {
        try {
            $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $order = Mage::getModel('sales/order')->load($orderId);
            $general_settings = json_decode($this->_helper->getConfig('master_settings_field'))->general;
            $store = Mage::app()->getStore();
            $data = $this->_orderData->getInvitation($order, 'magento1_success', Trustpilot_Reviews_Model_Config::WITH_PRODUCT_DATA, $store);
            try {
                $data['totalCost'] = $order->getGrandTotal();
                $data['currency'] = $order->getOrderCurrencyCode();
            } catch (\Exception $ex) {}

            if (!in_array('trustpilotOrderConfirmed', $general_settings->mappedInvitationTrigger)) {
                $data['payloadType'] = 'OrderStatusUpdate';
            }

            return json_encode($data, JSON_HEX_APOS);
        } catch (Exception $e) {
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
