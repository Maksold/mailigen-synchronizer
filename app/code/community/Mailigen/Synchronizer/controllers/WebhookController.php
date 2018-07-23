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
        $this->l()->setLogFile(Mailigen_Synchronizer_Helper_Log::WEBHOOK_LOG_FILE);
        $this->l()->log('============================');

        if (!$this->h()->enabledWebhooks()) {
            $this->l()->log('Webhooks are disabled.');
            return '';
        }

        if (!$this->getRequest()->isPost()) {
            $requestMethod = $this->getRequest()->getMethod();
            $this->l()->log("Request should be a 'POST' method, instead of '{$requestMethod}'.");
            return '';
        }

        $data = $this->getRequest()->getRawBody();
        $signature = $this->getRequest()->getHeader('X-Mailigen-Signature');
        if (!$this->h()->verifyWebhooksSignature($data, $signature)) {
            $this->l()->log("Data signature is incorrect.");
            return '';
        }

        $this->l()->log("Webhook called with data: " . $data);

        try {
            $json = json_decode($data);

            if (!isset($json->hook) || !isset($json->data)) {
                $this->l()->log('No hook or data in JSON.');
                return '';
            }

            switch ($json->hook) {
                case 'contact.subscribe':
                    /**
                     * Subscribe contact
                     */
                    $this->l()->log('Called: _subscribeContact()');
                    $this->_subscribeContact($json->data);
                    break;
                case 'contact.unsubscribe':
                    /**
                     * Unsubscribe contact
                     */
                    $this->l()->log('Called: _unsubscribeContact()');
                    $this->_unsubscribeContact($json->data);
                    break;
                default:
                    $this->l()->log("Hook '{$json->hook}' is not supported");
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
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
        $check = $this->h()->getContactList() == $listId;

        if (!$check) {
            $this->l()->log('Contact list with Id "' . $listId . '" doesn\'t exist');
        }

        return $check;
    }

    /**
     * Subscribe webhook
     *
     * @todo Subscribe to necessary Website Id
     * @param $data
     * @throws Mage_Core_Exception
     * @throws Exception
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
                $this->l()->log("Contact is already subscribed with email: $email");
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

                    $this->l()->log("Subscribed contact with email: $email");
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

                    $this->l()->log("Unsubscribed contact with email: $email");
                } else {
                    Mage::throwException("Can't unsubscribe contact with email: $email");
                }

                Mage::unregister('mailigen_webhook');
            } else {
                $this->l()->log("Subscriber doesn't exist with email: $email");
            }
        }
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Data
     */
    protected function h()
    {
        return Mage::helper('mailigen_synchronizer');
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Log
     */
    protected function l()
    {
        return Mage::helper('mailigen_synchronizer/log');
    }
}