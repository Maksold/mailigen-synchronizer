<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Adminhtml_MailigenController extends Mage_Adminhtml_Controller_Action
{
    public function syncNewsletterAction()
    {
        try {
            /** @var $helper Mailigen_Synchronizer_Helper_Data */
            $helper = Mage::helper('mailigen_synchronizer');
            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->countPendingOrRunningJobs() == 0) {
                $mailigenSchedule->createJob();
                $helper->setManualSync(1);
            }

            $this->_getSession()->addSuccess($this->__('Mailigen newsletter synchronization task will start shortly.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        $this->_redirect('*/newsletter_subscriber/index');
    }

    public function syncCustomersAction()
    {
        try {
            /** @var $helper Mailigen_Synchronizer_Helper_Data */
            $helper = Mage::helper('mailigen_synchronizer');
            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->countPendingOrRunningJobs() == 0) {
                $mailigenSchedule->createJob();
                $helper->setManualSync(1);
            }

            $this->_getSession()->addSuccess($this->__('Mailigen customer synchronization task will start shortly.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        $this->_redirect('*/customer/index');
    }

    /**
     * Force stop customer sync
     */
    public function stopSyncAction()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $helper->setStopSync(1);

        $this->getResponse()->setBody($this->__('Sync will be stopped within a minute'));
    }

    /**
     * Force set customers not synced, to allow sync again
     */
    public function resetSyncCustomersAction()
    {
        /** @var $customer Mailigen_Synchronizer_Model_Customer */
        $customer = Mage::getModel('mailigen_synchronizer/customer');
        $customer->setCustomersNotSynced();

        $this->getResponse()->setBody('1');
    }

    /**
     * Force set newsletter not synced, to allow sync again
     */
    public function resetSyncNewsletterAction()
    {
        /** @var $customer Mailigen_Synchronizer_Model_Newsletter */
        $newsletter = Mage::getModel('mailigen_synchronizer/newsletter');
        $newsletter->setNewsletterNotSynced();

        $this->getResponse()->setBody('1');
    }
}