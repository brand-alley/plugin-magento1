<?php

class Trustpilot_Reviews_Helper_Updater extends Mage_Core_Helper_Abstract
{
    public static function trustpilotGetPlugins($plugins)
    {
        $args = array(
            'path' => Mage::getBaseDir('base'),
            'trustpilot_preserve_zip' => false
        );

        foreach($plugins as $plugin) {
            $source = $plugin['path'];
            $target = $args['path'] . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . $plugin['name'] . '.tgz';
            
            Mage::log('Updating Trustpilot reviews plugin. Source: ' . $source . ', target: ' . $target, 2);

            if (file_exists($target)) {
                unlink($target);
            }

            self::trustpilotPluginDownload($source, $target);
            self::trustpilotPluginUnpack($args, $target);
            self::trustpilotPluginActivate($plugin['name']);
        }
    }

    private static function trustpilotPluginDownload($url, $path) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        if (file_put_contents($path, $data))
            return true;
        else
            return false;
    }

    private static function trustpilotPluginUnpack($args, $target) {
        $package = new Mage_Connect_Package($target);
        $config = new Mage_Connect_Config();
        if (empty($config->magento_root)) {
            $config->magento_root = $args['path'];
        }
        $packager = new Mage_Connect_Packager();
        $packager->processInstallPackage($package, $target, $config);

        if ($args['trustpilot_preserve_zip'] === false) {
            unlink($target);
        }
    }

    private static function trustpilotPluginActivate($installer) {
        Mage::app()->getCache()->deletePackage($chan, $pack);
        Mage::app()->getCache()->addPackage($package);
    }
}
