<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Resource_Customer_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('mailigen_synchronizer/customer');
    }

    /**
     * Retrieve all ids for collection
     *
     * @param bool $isSynced
     * @param bool $isRemoved
     * @param int  $storeId
     * @return array
     */
    public function getAllIds($isSynced = false, $isRemoved = false, $storeId = null)
    {
        $idsSelect = clone $this->getSelect();
        $idsSelect->reset(Zend_Db_Select::ORDER);
        $idsSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $idsSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $idsSelect->reset(Zend_Db_Select::COLUMNS);

        if (is_int($isSynced)) {
            $idsSelect->where('is_synced = ?', $isSynced);
        }

        if (is_int($isRemoved)) {
            $idsSelect->where('is_removed = ?', $isRemoved);
        }

        if (null !== $storeId) {
            $idsSelect->where('store_id = ?', $storeId);
        }

        $idsSelect->columns($this->getResource()->getIdFieldName(), 'main_table');
        return $this->getConnection()->fetchCol($idsSelect);
    }
}