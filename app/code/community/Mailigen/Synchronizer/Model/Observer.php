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
     * Add original data to subscriber model
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function newsletterSubscriberSaveBefore(Varien_Event_Observer $observer)
    {
        /**
         * Check if it was webhook save
         */
        if (Mage::registry('mailigen_webhook')) {
            return $this;
        }

        $subscriber = $observer->getDataObject();

        try {
            if ($subscriber
                && !$subscriber->isObjectNew()
                && !$subscriber->getOrigData()
                && $this->h()->isEnabled($subscriber->getStoreId())
            ) {
                $origSusbcriber = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());

                if ($origSusbcriber) {
                    foreach ($origSusbcriber->getData() as $k => $v) {
                        $subscriber->setOrigData($k, $v);
                    }
                }
            }
        } catch (Exception $e) {
            $this->l()->logException($e);
        }

        return $this;
    }

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
                $subscriberStatus = (int)$subscriber->getData('subscriber_status');
                $storeId = $subscriber->getStoreId();
                $api = $this->h()->getMailigenApi($storeId);
                $listId = $this->h()->getContactList($storeId);
                if (!$listId) {
                    $this->l()->log('Contact list isn\'t selected');
                    return $this;
                }

                $email = $subscriber->getEmail();

                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/mailigen_merge_field')
                    ->setStoreId($storeId)
                    ->createMergeFields();
                $this->l()->log('Merge fields created and updated');

                if ($subscriberStatus === Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                    /**
                     * Subscribe newsletter
                     */
                    /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
                    $customerHelper = Mage::helper('mailigen_synchronizer/customer');
                    $website = $customerHelper->getWebsite($storeId);
                    $subscriber->setWebsiteId($website ? $website->getId() : 0);
                    $subscriber->setType(Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_GUEST_TYPE);

                    // Prepare Merge vars
                    $mergeVars = array(
                        'EMAIL' => $subscriber->getEmail(),
                    );

                    // If is a customer we also grab firstname and lastname
                    if ($subscriber->getCustomerId()) {
                        $subscriber->setType(Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_CUSTOMER_TYPE);

                        $customer = Mage::getModel('customer/customer')->load($subscriber->getCustomerId());
                        $mergeVars['FNAME'] = $customer->getFirstname();
                        $mergeVars['LNAME'] = $customer->getLastname();
                    }

                    // Prepare basic mapped Merge vars
                    $mappedFields = $this->mapfieldHelper()->getBasicMappedFields($storeId);
                    foreach ($mappedFields as $_attributeCode => $_fieldTitle) {
                        $mergeVars[$_fieldTitle] = $this->mapfieldHelper()->getMappedFieldValue($_attributeCode, $subscriber);
                    }

                    $canHandleDefaultEmails = $this->h()->canHandleDefaultEmails($storeId);

                    $retval = $api->listSubscribe($listId, $email, $mergeVars, 'html', false, true, $canHandleDefaultEmails);
                    $this->l()->log('Subscribed newsletter with email: ' . $email);

                } elseif ($subscriberStatus === Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                    /**
                     * Unsubscribe newsletter
                     */
                    $canHandleDefaultEmails = $this->h()->canHandleDefaultEmails($storeId);
                    $retval = $api->listUnsubscribe($listId, $email, false, $canHandleDefaultEmails, true);
                    $this->l()->log('Unsubscribed newsletter with email: ' . $email);

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
                    $this->l()->log("Unable to (un)subscribe newsletter with email: $email. $api->errorCode: $api->errorMessage");
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

                $email = $subscriber->getEmail();

                /**
                 * Remove subscriber
                 */
                $canHandleDefaultEmails = $this->h()->canHandleDefaultEmails($storeId);
                $retval = $api->listUnsubscribe($listId, $email, true, $canHandleDefaultEmails, true);
                $this->l()->log('Remove subscriber with email: ' . $email);

                if ($retval) {
                    // Set customer not synced
                    if ($subscriber->getCustomerId()) {
                        Mage::getModel('mailigen_synchronizer/customer')->setNotSynced($subscriber->getCustomerId());
                    }
                } elseif (null !== $retval) {
                    $this->l()->log("Unable to remove subscriber with email: $email. $api->errorCode: $api->errorMessage");
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
                            $canHandleDefaultEmails = $this->h()->canHandleDefaultEmails();
                            $retval = $api->listUnsubscribe($listId, $oldEmail, true, $canHandleDefaultEmails, true);
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

    /**
     * @return Mailigen_Synchronizer_Helper_Mapfield
     */
    protected function mapfieldHelper()
    {
        return Mage::helper('mailigen_synchronizer/mapfield');
    }
}