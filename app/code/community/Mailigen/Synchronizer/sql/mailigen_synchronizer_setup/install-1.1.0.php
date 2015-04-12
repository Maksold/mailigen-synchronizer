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
 * Drop table 'mailigen_synchronizer/customer' if it exists
 */
$installer->getConnection()->dropTable($installer->getTable('mailigen_synchronizer/customer'));

/**
 * Create table 'mailigen_synchronizer/customer'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('mailigen_synchronizer/customer'))
    // Customer info fields
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true,), 'Customer Id'
    )
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    // Customer order fields
    ->addColumn('lastorderdate', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('valueoflastorder', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('totalvalueoforders', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('totalnumberoforders', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('numberofitemsincart', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('valueofcurrentcart', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    ->addColumn('lastitemincartaddingdate', Varien_Db_Ddl_Table::TYPE_TEXT, 255)
    // Special fields
    ->addColumn('is_removed', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array('default' => '0'))
    ->addColumn('is_synced', Varien_Db_Ddl_Table::TYPE_TINYINT, 1, array('default' => '0'))
    ->addColumn('synced_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP)
    ->setComment('Mailigen Synchronizer Customers');

$installer->getConnection()->createTable($table);

$installer->endSetup();