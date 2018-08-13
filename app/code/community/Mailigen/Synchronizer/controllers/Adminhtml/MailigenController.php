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
            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->getLastRunningJob() === false) {
                $mailigenSchedule->createJob();
            }

            $this->_getSession()->addSuccess($this->__('Mailigen synchronization task will start shortly.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        $this->_redirect('*/newsletter_subscriber/index');
    }

    public function syncCustomersAction()
    {
        try {
            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->getLastRunningJob() === false) {
                $mailigenSchedule->createJob();
            }

            $this->_getSession()->addSuccess($this->__('Mailigen synchronization task will start shortly.'));
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
        Mage::getModel('mailigen_synchronizer/customer')->setAllNotSynced();

        $this->getResponse()->setBody('1');
    }

    /**
     * Force set newsletter not synced, to allow sync again
     */
    public function resetSyncNewsletterAction()
    {
        Mage::getModel('mailigen_synchronizer/guest')->setAllNotSynced();

        $this->getResponse()->setBody('1');
    }

    /**
     * Generate new webhooks secret key
     */
    public function generateSecretKeyAction()
    {
        $storeId = $this->getRequest()->getParam('storeId');

        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $secretKey = $helper->generateWebhooksSecretKey($storeId);

        $this->getResponse()->setBody($secretKey);
    }

    public function resetMapFieldsAction()
    {
        $scope = $this->getRequest()->getParam('scope');
        $storeId = $this->getRequest()->getParam('scopeId');

        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        $mapFields = json_encode($helper->getMapFields($storeId));
        $defaultMapFields = json_encode($helper->getDefaultMapFields($storeId));

        if ($mapFields !== $defaultMapFields) {
            Mage::getConfig()->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_MAP_FIELDS, $defaultMapFields, $scope, $storeId);

            // Reinit config
            Mage::getConfig()->reinit();
            Mage::app()->reinitStores();
        }

        $this->_getSession()->addSuccess($this->__('Customer fields mapping config was reset.'));

        $this->getResponse()->setBody('1');
    }

    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/mailigen_synchronizer');
    }
}