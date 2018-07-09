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
     * Sync newsletter and customers by cron job
     *
     * @return $this|string
     */
    public function sync()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if (!$helper->isEnabled()) {
            return 'Module is disabled';
        }

        /**
         * Synchronize Newsletter
         */
        try {
            if ($helper->canAutoSyncNewsletter()) {
                /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
                $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
                $mailigen->syncNewsletter();
            }
        } catch (Exception $e) {
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        /**
         * Synchronize Customers
         */
        try {
            if ($helper->canAutoSyncCustomers() || $helper->getManualSync()) {
                if ($helper->getManualSync()) {
                    $helper->setManualSync(0);
                }

                /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
                $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
                $mailigen->syncCustomers();
            }
        } catch (Exception $e) {
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        return $this;
    }
}