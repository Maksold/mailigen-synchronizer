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
 * Add 'mailigen_synced' column to 'newsletter_subscriber' table
 */
$tableName = $this->getTable('newsletter_subscriber');
$installer->getConnection()->addColumn($tableName, 'mailigen_synced', 'tinyint(1) NOT NULL DEFAULT 0');


$installer->endSetup();