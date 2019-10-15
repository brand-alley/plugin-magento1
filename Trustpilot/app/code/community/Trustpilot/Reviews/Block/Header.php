<?php

class Trustpilot_Reviews_Block_Header extends Mage_Core_Block_Template
{
    protected $_scriptUrl;
    protected $_tbWidgetScriptUrl;
    protected $_previewScriptUrl;
    protected $_previewCssUrl;
    protected $_helper;
    protected $_sentryLogUrl;

    public function __construct()
    {
        $this->_helper                  = Mage::helper('trustpilot/Data');
        $this->_scriptUrl               = Trustpilot_Reviews_Model_Config::TRUSTPILOT_SCRIPT_URL;
        $this->_tbWidgetScriptUrl       = Trustpilot_Reviews_Model_Config::TRUSTPILOT_WIDGET_SCRIPT_URL;
        $this->_previewScriptUrl        = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PREVIEW_SCRIPT_URL;
        $this->_previewCssUrl           = Trustpilot_Reviews_Model_Config::TRUSTPILOT_PREVIEW_CSS_URL;
        $this->_sentryLogUrl            = Trustpilot_Reviews_Model_Config::TRUSTPILOT_SENTRY_LOG_URL;
        parent::__construct();
    }

    public function getScriptUrl()
    {
        return $this->_scriptUrl;
    }

    public function getWidgetScriptUrl()
    {
        return $this->_tbWidgetScriptUrl;
    }

    public function getPreviewScriptUrl()
    {
        return $this->_previewScriptUrl;
    }

    public function getPreviewCssUrl()
    {
        return $this->_previewCssUrl;
    }

    public function getInstallationKey()
    {
        return $this->_helper->getKey();
    }

    public function getSentryUrl()
    {
        return $this->_sentryLogUrl;
    }
}