<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_System_Config_Backend_Contact_List extends Mage_Core_Model_Config_Data
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
        $oldValue = $helper->getContactList();
        $newValue = $this->getValue();

        if ($oldValue != $newValue) {

            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->getLastRunningJob()) {
                /**
                 * Deny config modification, until synchronization will not be finished
                 */
                $this->_dataSaveAllowed = false;
                Mage::getSingleton('adminhtml/session')->addNotice($helper->__("You can't change contact list until synchronization will not be finished."));

            } else {

                /**
                 * Set guests not synced on contact list change
                 */
                Mage::getModel('mailigen_synchronizer/guest')->setAllNotSynced();

                /**
                 * Set customers not synced on contact list change
                 */
                Mage::getModel('mailigen_synchronizer/customer')->setAllNotSynced();
            }
        }
    }
}