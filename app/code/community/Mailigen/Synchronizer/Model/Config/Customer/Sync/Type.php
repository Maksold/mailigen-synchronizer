<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Config_Customer_Sync_Type extends Mage_Core_Model_Config_Data
{
    const SYNC_ALL_CUSTOMERS = 1;
    const SYNC_SUBSCRIBED_CUSTOMERS = 2;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('label' => 'Sync all customers', 'value' => self::SYNC_ALL_CUSTOMERS),
            array('label' => 'Sync only subscribed customers', 'value' => self::SYNC_SUBSCRIBED_CUSTOMERS),
        );
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $oldValue = $helper->getCustomerSyncType();
        $newValue = $this->getValue();

        if ($oldValue != $newValue) {

            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->getLastRunningJob()) {
                /**
                 * Deny config modification, until synchronization will not be finished
                 */
                $this->_dataSaveAllowed = false;
                Mage::getSingleton('adminhtml/session')->addNotice($helper->__('You can\'t change customer sync type until synchronization will not be finished. Please try again after ~10 seconds.'));

                $helper->setStopSync(1);

            } else {

                /**
                 * Set customers not synced after customer sync type change
                 */
                Mage::getModel('mailigen_synchronizer/customer')->setAllNotSynced();
            }
        }

        return $this;
    }
}