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

/**
 * Add 'website_id' column to 'mailigen_synchronizer/customer' table
 */
$installer->run("
ALTER TABLE {$installer->getTable('mailigen_synchronizer/customer')}
    ADD `website_id` SMALLINT(5) UNSIGNED NOT NULL AFTER `email`;
");

$installer->endSetup();