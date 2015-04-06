<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Helper_Customer extends Mage_Core_Helper_Abstract
{
    /**
     * @var array
     */
    protected $_storeLang = array();

    /**
     * @var null|array
     */
    protected $_customerGroup = null;

    /**
     * @param $date
     * @return bool|string
     */
    public function getFormattedDate($date)
    {
        if (is_numeric($date)) {
            $date = date('d/m/Y', $date);
        } elseif (is_string($date) && !empty($date)) {
            $date = date('d/m/Y', strtotime($date));
        } else {
            $date = '';
        }
        return $date;
    }

    public function getFormattedGender($gender)
    {
        if (!is_null($gender)) {
            // @todo
        } else {
            return '';
        }
    }

    /**
     * @param $groupId
     * @return string
     */
    public function getCustomerGroup($groupId)
    {
        if (is_null($this->_customerGroup)) {
            $this->_customerGroup = array();
            /** @var $groups Mage_Customer_Model_Resource_Group_Collection */
            $groups = Mage::getModel('customer/group')->getCollection();
            foreach ($groups as $group) {
                $this->_customerGroup[$group->getCustomerGroupId()] = $group->getCustomerGroupCode();
            }
        }

        return isset($this->_customerGroup[$groupId]) ? $this->_customerGroup[$groupId] : '';
    }

    /**
     * @param $status
     * @return string
     */
    public function getFormattedCustomerStatus($status)
    {
        return $status ? 'Active' : 'Inactive';
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getStoreLanguage($storeId)
    {
        if (!isset($this->_storeLang[$storeId])) {
            $this->_storeLang[$storeId] = substr(Mage::getStoreConfig('general/locale/code', $storeId), 0, 2);
        }
        return $this->_storeLang[$storeId];
    }
}