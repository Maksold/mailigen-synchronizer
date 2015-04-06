<?php

class Mailigen_Synchronizer_Adminhtml_MailigenController extends Mage_Adminhtml_Controller_Action
{
    public function syncNewsletterAction()
    {
        /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
        $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
        $mailigen->syncNewsletter();

        $this->_redirect('*/newsletter_subscriber/index');
    }

    public function syncCustomersAction()
    {
        try {
            /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
            $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
            $mailigen->syncCustomers();
        }
        catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::logException($e); // @todo Log to custom log file
        }

        $this->_redirect('*/customer/index');
    }
}