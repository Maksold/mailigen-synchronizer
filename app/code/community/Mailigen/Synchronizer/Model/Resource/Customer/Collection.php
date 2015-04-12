<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Resource_Customer_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('mailigen_synchronizer/customer');
    }

    /**
     * Retrieve all ids for collection
     *
     * @param bool $is_synced
     * @param bool $is_removed
     * @return array
     */
    public function getAllIds($is_synced = false, $is_removed = false)
    {
        $idsSelect = clone $this->getSelect();
        $idsSelect->reset(Zend_Db_Select::ORDER);
        $idsSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $idsSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $idsSelect->reset(Zend_Db_Select::COLUMNS);

        if (is_int($is_synced)) {
            $idsSelect->where('is_synced = ?', $is_synced);
        }
        if (is_int($is_removed)) {
            $idsSelect->where('is_removed = ?', $is_removed);
        }

        $idsSelect->columns($this->getResource()->getIdFieldName(), 'main_table');
        return $this->getConnection()->fetchCol($idsSelect);
    }
}