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
$tableName = $installer->getTable('mailigen_synchronizer/customer');
$installer->getConnection()->addColumn($tableName, 'website_id', 'SMALLINT(5) UNSIGNED NOT NULL AFTER `email`');

$installer->endSetup();