<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_System_Config_Backend_Customer_List extends Mage_Core_Model_Config_Data
{
    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $oldValue = $helper->getCustomersContactList();
        $newValue = $this->getValue();

        /**
         * Set customers not synced on contact list change
         */
        if (!empty($newValue) && $oldValue != $newValue) {
            /** @var $customer Mailigen_Synchronizer_Model_Customer */
            $customer = Mage::getModel('mailigen_synchronizer/customer');
            $customer->setCustomersNotSynced();
        }
    }
}