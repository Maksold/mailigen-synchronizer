<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Cron
{

    /**
     * Sync guests and customers by cron job
     *
     * @return $this|string
     */
    public function sync()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $log->setLogFile(Mailigen_Synchronizer_Helper_Log::SYNC_LOG_FILE);
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if (!$helper->isEnabled()) {
            return 'Module is disabled in config';
        }

        /**
         * Synchronize Guests
         */
        try {
            /** @var $guestSync Mailigen_Synchronizer_Model_Sync_Guest */
            $guestSync = Mage::getModel('mailigen_synchronizer/sync_guest');
            $guestSync->sync();
        } catch (Exception $e) {
            $log->logException($e);
        }

        /**
         * Synchronize Customers
         */
        try {
            /** @var $customerSync Mailigen_Synchronizer_Model_Sync_Customer */
            $customerSync = Mage::getModel('mailigen_synchronizer/sync_customer');
            $customerSync->sync();
        } catch (Exception $e) {
            $log->logException($e);
        }

        return $this;
    }
}