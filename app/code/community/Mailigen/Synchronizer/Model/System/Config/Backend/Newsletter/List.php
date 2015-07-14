<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_System_Config_Backend_Newsletter_List extends Mage_Core_Model_Config_Data
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
        /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
        $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');
        $oldValue = $helper->getNewsletterContactList();
        $newValue = $this->getValue();

        if ($oldValue != $newValue) {
            /**
             * Deny config modification, until synchronization will not be finished
             */
            if ($mailigenSchedule->countPendingOrRunningJobs() > 0) {
                $this->_dataSaveAllowed = false;
                Mage::getSingleton('adminhtml/session')->addNotice($helper->__("You can't change newsletter list until synchronization will not be finished."));
            } else {
                /**
                 * Set newsletter not synced on contact list change
                 */
                /** @var $newsletter Mailigen_Synchronizer_Model_Newsletter */
                $newsletter = Mage::getModel('mailigen_synchronizer/newsletter');
                $newsletter->setNewsletterNotSynced();
            }
        }
    }
}