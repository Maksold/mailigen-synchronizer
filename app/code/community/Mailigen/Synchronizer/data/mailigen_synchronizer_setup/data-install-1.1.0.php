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
    'mailigen_settings/mailigen_general_group/mailigen_general_status' => Mailigen_Synchronizer_Helper_Data::XML_PATH_ENABLED,
    'mailigen_settings/mailigen_general_group/mailigen_general_api_key' => Mailigen_Synchronizer_Helper_Data::XML_PATH_API_KEY,
    'mailigen_settings/mailigen_general_group/mailigen_general_new_list' => Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_NEW_LIST_TITLE,
    'mailigen_settings/mailigen_general_group/mailigen_autosync_list' => Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_AUTOSYNC,
    'mailigen_settings/mailigen_general_group/mailigen_default_emails' => Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_HANDLE_DEFAULT_EMAILS,
    'mailigen_settings/mailigen_general_group/mailigen_general_list' => Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_CONTACT_LIST,
);

foreach ($configMapping as $oldConfig => $newConfig) {
    $oldConfigValue = Mage::getStoreConfig($oldConfig);

    $configModel->saveConfig($newConfig, $oldConfigValue);
    $configModel->deleteConfig($oldConfig);
}

$installer->endSetup();