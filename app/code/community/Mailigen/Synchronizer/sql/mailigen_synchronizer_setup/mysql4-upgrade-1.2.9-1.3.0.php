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
 * Add 'store_id' column to 'mailigen_synchronizer/customer' table
 */
$mailigenTableName = $installer->getTable('mailigen_synchronizer/customer');
$customerTableName = $installer->getTable('customer/entity');
$installer->getConnection()->addColumn($mailigenTableName, 'store_id', 'SMALLINT(5) UNSIGNED DEFAULT NULL AFTER `website_id`');


/*
 * Update 'mailigen_synchronizer_customer.store_id' field data from 'customer_entity table
 */
$sql = "UPDATE {$mailigenTableName} ms_customer, (SELECT entity_id, store_id FROM {$customerTableName}) customer
SET ms_customer.store_id = customer.store_id
WHERE ms_customer.id = customer.entity_id";

$installer->getConnection()->query($sql);

$installer->endSetup();