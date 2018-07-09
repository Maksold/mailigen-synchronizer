<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_WebhookController extends Mage_Core_Controller_Front_Action
{
    /**
     * Mailigen Webhooks handler action
     *
     * @return Mage_Core_Controller_Varien_Action|string
     * @throws Zend_Controller_Request_Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function indexAction()
    {
        $this->_getLog()->setLogFile(Mailigen_Synchronizer_Helper_Log::WEBHOOK_LOG_FILE);
        $this->_getLog()->log('============================');

        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if (!$helper->enabledWebhooks()) {
            $this->_getLog()->log('Webhooks are disabled.');
            return '';
        }

        if (!$this->getRequest()->isPost()) {
            $requestMethod = $this->getRequest()->getMethod();
            $this->_getLog()->log("Request should be a 'POST' method, instead of '{$requestMethod}'.");
            return '';
        }

        $data = $this->getRequest()->getRawBody();
        $signature = $this->getRequest()->getHeader('X-Mailigen-Signature');
        if (!$helper->verifySignature($data, $signature)) {
            $this->_getLog()->log("Data signature is incorrect.");
            return '';
        }

        $this->_getLog()->log("Webhook called with data: " . $data);

        try {
            $json = json_decode($data);

            if (!isset($json->hook) || !isset($json->data)) {
                $this->_getLog()->log('No hook or data in JSON.');
                return '';
            }

            switch ($json->hook) {
                case 'contact.subscribe':
                    /**
                     * Subscribe contact
                     */
                    $this->_getLog()->log('Called: _subscribeContact()');
                    $this->_subscribeContact($json->data);
                    break;
                case 'contact.unsubscribe':
                    /**
                     * Unsubscribe contact
                     */
                    $this->_getLog()->log('Called: _unsubscribeContact()');
                    $this->_unsubscribeContact($json->data);
                    break;
                default:
                    $this->_getLog()->log("Hook '{$json->hook}' is not supported");
            }
        } catch (Exception $e) {
            $this->_getLog()->logException($e);
            $this->getResponse()->setHttpResponseCode(500);
            $this->getResponse()->sendResponse();
            return $this;
        }

        return '';
    }

    /**
     * @todo Check Website Id by List Id
     * @param $listId
     * @return bool
     */
    protected function _checkListId($listId)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $check = $helper->getNewsletterContactList() == $listId;

        if (!$check) {
            $this->_getLog()->log("Newsletter doesn't exist with List Id: $listId");
        }

        return $check;
    }

    /**
     * Subscribe webhook
     *
     * @todo Subscribe to necessary Website Id
     * @param $data
     * @throws Mage_Core_Exception
     */
    protected function _subscribeContact($data)
    {
        if (count($data) <= 0) {
            return;
        }

        foreach ($data as $item) {
            if (!$this->_checkListId($item->list)) {
                continue;
            }

            $email = $item->email;

            /**
             * @todo Save First, Last name
             */
            $firstname = $item->fields->FNAME;
            $lastname = $item->fields->LNAME;

            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
            if ($subscriber && $subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                $this->_getLog()->log("Contact is already subscribed with email: $email");
            } else {
                /**
                 * Subscribe contact
                 */
                Mage::register('mailigen_webhook', true);
                $subscriberStatus = Mage::getModel('newsletter/subscriber')->subscribe($email);

                if ($subscriberStatus == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                    $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
                    if ($subscriber->getId()) {
                        Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId());
                    }

                    $this->_getLog()->log("Subscribed contact with email: $email");
                } else {
                    Mage::throwException("Can't subscribe contact with email: $email");
                }

                Mage::unregister('mailigen_webhook');
            }
        }
    }

    /**
     * Unsubscribe webhook
     *
     * @todo Unsubscribe from necessary Website Id
     * @param $data
     * @throws Mage_Core_Exception
     */
    protected function _unsubscribeContact($data)
    {
        if (count($data) <= 0) {
            return;
        }

        foreach ($data as $item) {
            if (!$this->_checkListId($item->list)) {
                continue;
            }

            $email = $item->email;

            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            if ($subscriber->getId()) {
                /**
                 * Unsubscribe contact
                 */
                Mage::register('mailigen_webhook', true);
                $subscriber->unsubscribe();

                if ($subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                    if ($subscriber->getId()) {
                        Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId());
                    }

                    $this->_getLog()->log("Unsubscribed contact with email: $email");
                } else {
                    Mage::throwException("Can't unsubscribe contact with email: $email");
                }

                Mage::unregister('mailigen_webhook');
            } else {
                $this->_getLog()->log("Subscriber doesn't exist with email: $email");
            }
        }
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Log
     */
    protected function _getLog()
    {
        return Mage::helper('mailigen_synchronizer/log');
    }
}