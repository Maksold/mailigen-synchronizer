<?php
/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$configModel = new Mage_Core_Model_Config();
$configMapping = array(
    'mailigen_settings/mailigen_general_group/mailigen_general_status'   => 'mailigen_synchronizer/general/enabled',
    'mailigen_settings/mailigen_general_group/mailigen_general_api_key'  => 'mailigen_synchronizer/general/api_key',
    'mailigen_settings/mailigen_general_group/mailigen_general_new_list' => 'mailigen_synchronizer/newsletter/new_list_title',
    'mailigen_settings/mailigen_general_group/mailigen_autosync_list'    => 'mailigen_synchronizer/newsletter/autosync',
    'mailigen_settings/mailigen_general_group/mailigen_default_emails'   => 'mailigen_synchronizer/newsletter/handle_default_emails',
    'mailigen_settings/mailigen_general_group/mailigen_general_list'     => 'mailigen_synchronizer/newsletter/contact_list',
);

foreach ($configMapping as $oldConfig => $newConfig) {
    $oldConfigValue = Mage::getStoreConfig($oldConfig);

    $configModel->saveConfig($newConfig, $oldConfigValue);
    $configModel->deleteConfig($oldConfig);
}

$installer->endSetup();