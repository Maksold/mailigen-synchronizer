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

$configTableName = $installer->getConnection()->getTableName('core_config_data');
$configMapping = array(
    'mailigen_synchronizer/customers/contact_list'           => 'mailigen_synchronizer/general/contact_list',
    'mailigen_synchronizer/customers/new_list_title'         => 'mailigen_synchronizer/general/contact_list_title',
    'mailigen_synchronizer/customers/autosync'               => null,
    'mailigen_synchronizer/newsletter/contact_list'          => null,
    'mailigen_synchronizer/newsletter/new_list_title'        => null,
    'mailigen_synchronizer/newsletter/handle_default_emails' => 'mailigen_synchronizer/general/handle_default_emails',
    'mailigen_synchronizer/newsletter/webhooks'              => 'mailigen_synchronizer/webhooks/enabled',
    'mailigen_synchronizer/newsletter/webhooks_secret_key'   => 'mailigen_synchronizer/webhooks/secret_key',
    'mailigen_synchronizer/newsletter/autosync'              => null,
);

foreach ($configMapping as $oldConfigPath => $newConfigPath) {

    $whereCondition = $installer->getConnection()->quoteInto('path = ?', $oldConfigPath);

    if ($newConfigPath === null) {
        /*
         * Remove old config path
         */
        $installer->getConnection()->delete($configTableName, $whereCondition);

    } else {
        /*
         * Update old config path to new config path
         */
        $installer->getConnection()->update($configTableName, array('path' => $newConfigPath), $whereCondition);

    }
}

$installer->endSetup();