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
    public function indexAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_redirect('/');
        }

        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');

        try {
            $json = $this->getRequest()->getRawBody();
            $json = json_decode($json);

            if (!isset($json->hook) || !isset($json->data)) {
                return $this->_redirect('/');
            }

            switch ($json->hook) {
                case 'contact.subscribe':
                    /**
                     * Subscribe contact
                     */
                    $this->_subscribeContact($json->data);
                    break;
                case 'contact.unsubscribe':
                    /**
                     * Unsubscribe contact
                     */
                    $this->_unsubscribeContact($json->data);
                    break;
            }
        } catch (Exception $e) {
            $logger->logException($e);
            return $this->_redirect('/');
        }
        return '';
    }

    /**
     * @todo Check Website Id
     * @param $listId
     * @return bool
     */
    protected function _checkListId($listId)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        return $helper->getNewsletterContactList() == $listId;
    }

    /**
     * @todo Test
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
            $firstname = $item->fields->FNAME;
            $lastname = $item->fields->LNAME;

            /**
             * @todo Save First, Last name
             */
            Mage::register('mailigen_webhook', true);
            $subscriberStatus = Mage::getModel('newsletter/subscriber')->subscribe($email);
            if ($subscriberStatus != Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                Mage::throwException("Can't subscribe customer with email: $email.");
            }
            Mage::register('mailigen_webhook', false);
        }
    }

    /**
     * @todo
     * @param $data
     */
    protected function _unsubscribeContact($data)
    {
        echo "2";
    }
}