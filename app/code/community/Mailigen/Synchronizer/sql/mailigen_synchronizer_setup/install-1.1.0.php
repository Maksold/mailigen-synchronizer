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
 * Drop and Create table 'mailigen_synchronizer/customer'
 */
$installer->run("
DROP TABLE IF EXISTS `{$installer->getTable('mailigen_synchronizer/customer')}`;
CREATE TABLE `{$installer->getTable('mailigen_synchronizer/customer')}` (
  `id` int(10) unsigned NOT NULL COMMENT 'Customer Id',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email',
  `lastorderdate` varchar(255) DEFAULT NULL COMMENT 'Last order date',
  `valueoflastorder` varchar(255) DEFAULT NULL COMMENT 'Value of last order',
  `totalvalueoforders` varchar(255) DEFAULT NULL COMMENT 'Total value of orders',
  `totalnumberoforders` varchar(255) DEFAULT NULL COMMENT 'Total number of orders',
  `numberofitemsincart` varchar(255) DEFAULT NULL COMMENT 'Number of items in cart',
  `valueofcurrentcart` varchar(255) DEFAULT NULL COMMENT 'Value of current cart',
  `lastitemincartaddingdate` varchar(255) DEFAULT NULL COMMENT 'Last item in cart adding date',
  `is_removed` tinyint(1) DEFAULT '0' COMMENT 'Is removed',
  `is_synced` tinyint(1) DEFAULT '0' COMMENT 'Is synced',
  `synced_at` timestamp NULL DEFAULT NULL COMMENT 'Synced at',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Mailigen Synchronizer Customers';
");

$installer->endSetup();