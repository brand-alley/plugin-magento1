<?
Mage::log('Running Trustpilot Reviews upgrade via: ' . get_class($this));

$installer = $this;

$installer->startSetup();

try {
    $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = $installer->getTable('core/config_data');
    $sql = "DELETE FROM {$table} WHERE path LIKE 'trustpilot/trustpilot_general_group/%';";
    $conn->query($sql);
} catch (Exception $e){
    Mage::log('Got an error while upgrading Trustpilot Reviews: ' . $e->getMessage());
}

$installer->endSetup();

Mage::log('Finished Trustpilot Reviews upgrade via: ' . get_class($this));
