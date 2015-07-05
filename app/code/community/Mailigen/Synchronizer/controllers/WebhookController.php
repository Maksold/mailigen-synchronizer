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

        if (!$this->getRequest()->isPost()) {
            $this->logger->logWebhook("It's not POST request.");
            return '';
        }

        $this->logger->logWebhook("Webhook called with data: " . $this->getRequest()->getRawBody());

        try {
            $json = $this->getRequest()->getRawBody();
            $json = json_decode($json);

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
            }
        } catch (Exception $e) {
            $this->logger->logWebhook('Exception: ' . $e->getMessage());
            return $this->_redirect('/');
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

            Mage::register('mailigen_webhook', true);

            $subscriberStatus = Mage::getModel('newsletter/subscriber')->subscribe($email);

            if ($subscriberStatus == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
                if ($subscriber->getId()) {
                    Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId());
                }

                $this->logger->logWebhook("Subscribed contact with email: $email");
            } else {
                $this->logger->logWebhook("Can't subscribe contact with email: $email");
                Mage::throwException("Can't subscribe contact with email: $email");
            }

            Mage::unregister('mailigen_webhook');
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

            Mage::register('mailigen_webhook', true);

            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
            $subscriber->unsubscribe();

            if ($subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                if ($subscriber->getId()) {
                    Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId());
                }
                $this->logger->logWebhook("Unsubscribed contact with email: $email");
            } else {
                $this->logger->logWebhook("Can't unsubscribe contact with email: $email");
                Mage::throwException("Can't unsubscribe contact with email: $email");
            }

            Mage::unregister('mailigen_webhook');
        }
    }
}