<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Observer
{
    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function newsletterSubscriberSaveCommitAfter(Varien_Event_Observer $observer)
    {
        /**
         * Check if it was webhook save
         */
        if (Mage::registry('mailigen_webhook')) {
            return $this;
        }

        $subscriber = $observer->getDataObject();

        try {
            if ($subscriber && $this->h()->isEnabled($subscriber->getStoreId())
                && ($subscriber->getIsStatusChanged() == true || $subscriber->getOrigData('subscriber_status') != $subscriber->getData('subscriber_status'))
            ) {
                $storeId = $subscriber->getStoreId();
                $api = $this->h()->getMailigenApi($storeId);
                $listId = $this->h()->getContactList($storeId);
                if (!$listId) {
                    $this->l()->log('Contact list isn\'t selected');
                    return $this;
                }

                $email_address = $subscriber->getSubscriberEmail();

                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/mailigen_merge_field')
                    ->setStoreId($storeId)
                    ->createMergeFields();
                $this->l()->log('Merge fields created and updated');

                if ($subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                    /**
                     * Subscribe newsletter
                     */
                    /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
                    $customerHelper = Mage::helper('mailigen_synchronizer/customer');

                    // Prepare Merge vars
                    $website = $customerHelper->getWebsite($storeId);
                    $merge_vars = array(
                        'EMAIL'          => $subscriber->getSubscriberEmail(),
                        'WEBSITEID'      => $website ? $website->getId() : 0,
                        'NEWSLETTERTYPE' => $customerHelper->getSubscriberType(Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_GUEST_TYPE),
                        'STOREID'        => $storeId,
                        'STORELANGUAGE'  => $customerHelper->getStoreLanguage($storeId),
                    );

                    // If is a customer we also grab firstname and lastname
                    if ($subscriber->getCustomerId()) {
                        $customer = Mage::getModel('customer/customer')->load($subscriber->getCustomerId());
                        $merge_vars['FNAME'] = $customer->getFirstname();
                        $merge_vars['LNAME'] = $customer->getLastname();
                        $merge_vars['NEWSLETTERTYPE'] = $customerHelper->getSubscriberType(Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_CUSTOMER_TYPE);
                    }

                    $send_welcome = $this->h()->canHandleDefaultEmails($storeId);

                    $retval = $api->listSubscribe($listId, $email_address, $merge_vars, 'html', false, true, $send_welcome);
                    $this->l()->log('Subscribed newsletter with email: ' . $email_address);
                } elseif ($subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                    /**
                     * Unsubscribe newsletter
                     */
                    $send_goodbye = $this->h()->canHandleDefaultEmails($storeId);
                    $retval = $api->listUnsubscribe($listId, $email_address, false, $send_goodbye, true);
                    $this->l()->log('Unsubscribed newsletter with email: ' . $email_address);
                } else {
                    // @todo Check Not Activated or Removed status?
                    $retval = null;
                }

                if ($retval) {
                    // Set subscriber synced
                    Mage::getModel('mailigen_synchronizer/guest')->setSynced($subscriber->getId());

                    // Set customer not synced
                    if ($subscriber->getCustomerId()) {
                        Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($subscriber->getCustomerId());
                    }
                } elseif (null !== $retval) {
                    $this->l()->log("Unable to (un)subscribe newsletter with email: $email_address. $api->errorCode: $api->errorMessage");
                }
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return Mailigen_Synchronizer_Model_Observer
     */
    public function newsletterSubscriberDeleteAfter(Varien_Event_Observer $observer)
    {
        $subscriber = $observer->getDataObject();

        try {
            if ($subscriber && $this->h()->isEnabled($subscriber->getStoreId())) {
                $storeId = $subscriber->getStoreId();
                $api = $this->h()->getMailigenApi($storeId);
                $listId = $this->h()->getContactList($storeId);
                if (!$listId) {
                    $this->l()->log('Contact list isn\'t selected');
                    return $this;
                }

                $email_address = $subscriber->getSubscriberEmail();

                /**
                 * Remove subscriber
                 */
                $send_goodbye = $this->h()->canHandleDefaultEmails($storeId);
                $retval = $api->listUnsubscribe($listId, $email_address, true, $send_goodbye, true);
                $this->l()->log('Remove subscriber with email: ' . $email_address);

                if ($retval) {
                    // Set customer not synced
                    if ($subscriber->getCustomerId()) {
                        Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($subscriber->getCustomerId());
                    }
                } elseif (null !== $retval) {
                    $this->l()->log("Unable to remove subscriber with email: $email_address. $api->errorCode: $api->errorMessage");
                }
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerDeleteAfter(Varien_Event_Observer $observer)
    {
        try {
            $customer = $observer->getDataObject();
            if ($customer && $customer->getId()) {
                Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($customer->getId(), 1);
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerSaveAfter(Varien_Event_Observer $observer)
    {
        try {
            $customer = $observer->getDataObject();
            if ($customer && $customer->getId()) {
                Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($customer->getId());

                $listId = $this->h()->getContactList();

                /**
                 * Check if Customer Firstname, Lastname or Email was changed
                 */
                if ($customer->getIsSubscribed() && $customer->hasDataChanges() && $this->h()->isEnabled() && !empty($listId)) {
                    $origCustomerData = $customer->getOrigData();

                    $nameChanged = ((isset($origCustomerData['firstname']) && $origCustomerData['firstname'] != $customer->getFirstname())
                        || (isset($origCustomerData['lastname']) && $origCustomerData['lastname'] != $customer->getLastname()));
                    $emailChanged = (isset($origCustomerData['email']) && !empty($origCustomerData['email']) && $origCustomerData['email'] != $customer->getEmail());

                    /**
                     * Set subscriber not synced, if customer Firstname, Lastname changed
                     */
                    if ($nameChanged && !$emailChanged) {
                        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());
                        if ($subscriber->getId()) {
                            Mage::getModel('mailigen_synchronizer/guest')->setNotSynced($subscriber->getId());
                        }
                    }

                    /**
                     * Unsubscribe customer with old email
                     */
                    if ($emailChanged) {
                        $oldEmail = $origCustomerData['email'];
                        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($oldEmail);

                        if ($subscriber->getId()) {
                            $api = $this->h()->getMailigenApi();

                            /**
                             * Remove subscriber
                             */
                            $send_goodbye = $this->h()->canHandleDefaultEmails();
                            $retval = $api->listUnsubscribe($listId, $oldEmail, true, $send_goodbye, true);
                            $this->l()->log('Remove subscriber with email: ' . $oldEmail);

                            if (!$retval) {
                                $this->l()->log("Unable to remove subscriber with email: $oldEmail. $api->errorCode: $api->errorMessage");
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerAddressSaveAfter(Varien_Event_Observer $observer)
    {
        try {
            $customerAddress = $observer->getDataObject();
            $customer = $customerAddress->getCustomer();
            if ($customer && $customer->getId()) {
                Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($customer->getId());
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerLogin(Varien_Event_Observer $observer)
    {
        try {
            $customer = $observer->getCustomer();
            if ($customer && $customer->getId()) {
                Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($customer->getId());
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function salesOrderSaveAfter(Varien_Event_Observer $observer)
    {
        try {
            $order = $observer->getOrder();
            if ($order && $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE && $order->getCustomerId()) {
                Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($order->getCustomerId());
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
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