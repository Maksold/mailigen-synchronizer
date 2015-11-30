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
     * @var null|Mailigen_Synchronizer_Helper_Log
     */
    public $logger = null;

    /**
     * Mailigen Webhooks handler action
     *
     * @return Mage_Core_Controller_Varien_Action|string
     */
    public function indexAction()
    {
        $this->logger = Mage::helper('mailigen_synchronizer/log');
        $this->logger->logWebhook('============================');

        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if (!$helper->enabledWebhooks()) {
            $this->logger->logWebhook('Webhooks are disabled.');
            return '';
        }

        if (!$this->getRequest()->isPost()) {
            $requestMethod = $this->getRequest()->getMethod();
            $this->logger->logWebhook("Request should be a 'POST' method, instead of '{$requestMethod}'.");
            return '';
        }

        $data = $this->getRequest()->getRawBody();
        $signature = $this->getRequest()->getHeader('X-Mailigen-Signature');
        if (!$helper->verifySignature($data, $signature)) {
            $this->logger->logWebhook("Data signature is incorrect.");
            return '';
        }

        $this->logger->logWebhook("Webhook called with data: " . $data);

        try {
            $json = json_decode($data);

            if (!isset($json->hook) || !isset($json->data)) {
                $this->logger->logWebhook('No hook or data in JSON.');
                return '';
            }

            switch ($json->hook) {
                case 'contact.subscribe':
                    /**
                     * Subscribe contact
                     */
                    $this->logger->logWebhook('Called: _subscribeContact()');
                    $this->_subscribeContact($json->data);
                    break;
                case 'contact.unsubscribe':
                    /**
                     * Unsubscribe contact
                     */
                    $this->logger->logWebhook('Called: _unsubscribeContact()');
                    $this->_unsubscribeContact($json->data);
                    break;
                default:
                    $this->logger->logWebhook("Hook '{$json->hook}' is not supported");
            }
        } catch (Exception $e) {
            $this->_returnError('Exception: ' . $e->getMessage());
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
            $this->logger->logWebhook("Newsletter doesn't exist with List Id: $listId");
        }

        return $check;
    }

    /**
     * Subscribe webhook
     * @todo Subscribe to necessary Website Id
     * @param $data
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
                $this->logger->logWebhook("Contact is already subscribed with email: $email");
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

                    $this->logger->logWebhook("Subscribed contact with email: $email");
                } else {
                    $this->_returnError("Can't subscribe contact with email: $email");
                }
                Mage::unregister('mailigen_webhook');
            }
        }
    }

    /**
     * Unsubscribe webhook
     * @todo Unsubscribe from necessary Website Id
     * @param $data
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
                    $this->logger->logWebhook("Unsubscribed contact with email: $email");
                } else {
                    $this->_returnError("Can't unsubscribe contact with email: $email");
                }
                Mage::unregister('mailigen_webhook');
            } else {
                $this->logger->logWebhook("Subscriber doesn't exist with email: $email");
            }
        }
    }

    /**
     * @param $message
     */
    protected function _returnError($message)
    {
        $this->logger->logWebhook($message);
        $this->getResponse()->setHttpResponseCode(500);
        $this->getResponse()->sendResponse();
        exit;
    }
}