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
     * @return Varien_Event_Observer
     */
    public function newsletterSubscriberSaveCommitAfter(Varien_Event_Observer $observer)
    {
        /**
         * Check if it was webhook save
         */
        if (Mage::registry('mailigen_webhook')) {
            return;
        }

        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        $subscriber = $observer->getDataObject();

        if ($helper->isEnabled() && $subscriber
            && ($subscriber->getIsStatusChanged() == true || $subscriber->getOrigData('subscriber_status') != $subscriber->getData('subscriber_status'))
        ) {
            $api = $helper->getMailigenApi();
            $newsletterListId = $helper->getNewsletterContactList();
            if (!$newsletterListId) {
                $logger->log('Newsletter contact list isn\'t selected');
                return;
            }
            $email_address = $subscriber->getSubscriberEmail();

            /**
             * Create or update Merge fields
             */
            Mage::getModel('mailigen_synchronizer/newsletter_merge_field')->createMergeFields();
            $logger->log('Newsletter merge fields created and updated');

            if ($subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) {
                /**
                 * Subscribe newsletter
                 */
                /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
                $customerHelper = Mage::helper('mailigen_synchronizer/customer');

                // Prepare Merge vars
                $website = $customerHelper->getWebsite($subscriber->getStoreId());
                $merge_vars = array(
                    'EMAIL' => $subscriber->getSubscriberEmail(),
                    'WEBSITEID' => $website ? $website->getId() : 0,
                    'TYPE' => $customerHelper->getSubscriberType(1),
                    'STOREID' => $subscriber->getStoreId(),
                    'STORELANGUAGE' => $customerHelper->getStoreLanguage($subscriber->getStoreId()),
                );

                // If is a customer we also grab firstname and lastname
                if ($subscriber->getCustomerId()) {
                    $customer = Mage::getModel('customer/customer')->load($subscriber->getCustomerId());
                    $merge_vars['FNAME'] = $customer->getFirstname();
                    $merge_vars['LNAME'] = $customer->getLastname();
                    $merge_vars['TYPE'] = $customerHelper->getSubscriberType(2);
                }

                $send_welcome = $helper->canNewsletterHandleDefaultEmails();

                $retval = $api->listSubscribe($newsletterListId, $email_address, $merge_vars, 'html', false, true, $send_welcome);
                $logger->log('Subscribed newsletter with email: ' . $email_address);
            }
            elseif ($subscriber->getSubscriberStatus() === Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                /**
                 * Unsubscribe newsletter
                 */
                $send_goodbye = $helper->canNewsletterHandleDefaultEmails();
                $retval = $api->listUnsubscribe($newsletterListId, $email_address, false, $send_goodbye, true);
                $logger->log('Unsubscribed newsletter with email: ' . $email_address);
            } else {
                // @todo Check Not Activated or Removed status?
                $retval = null;
            }

            if ($retval) {
                // Set subscriber synced
                Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId(), true);

                // Set customer not synced
                if ($subscriber->getCustomerId()) {
                    Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($subscriber->getCustomerId());
                }
            } elseif (!is_null($retval)) {
                $logger->log("Unable to (un)subscribe newsletter with email: $email_address. $api->errorCode: $api->errorMessage");
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function newsletterSubscriberDeleteAfter(Varien_Event_Observer $observer)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        $subscriber = $observer->getDataObject();

        if ($helper->isEnabled() && $subscriber) {
            $api = $helper->getMailigenApi();
            $newsletterListId = $helper->getNewsletterContactList();
            if (!$newsletterListId) {
                $logger->log('Newsletter contact list isn\'t selected');
                return;
            }
            $email_address = $subscriber->getSubscriberEmail();

            /**
             * Remove subscriber
             */
            $send_goodbye = $helper->canNewsletterHandleDefaultEmails();
            $retval = $api->listUnsubscribe($newsletterListId, $email_address, true, $send_goodbye, true);
            $logger->log('Remove subscriber with email: ' . $email_address);

            if ($retval) {
                // Set customer not synced
                if ($subscriber->getCustomerId()) {
                    Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($subscriber->getCustomerId());
                }
            } elseif (!is_null($retval)) {
                $logger->log("Unable to remove subscriber with email: $email_address. $api->errorCode: $api->errorMessage");
            }
        }
    }

    /**
     * Sync newsletter and customers by cron job
     */
    public function daily_sync()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if (!$helper->isEnabled()) {
            return "Module is disabled";
        }

        /**
         * Synchronize Newsletter
         */
        try {
            if ($helper->canAutoSyncNewsletter()) {
                /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
                $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
                $mailigen->syncNewsletter();
            }
        } catch (Exception $e) {
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }

        /**
         * Synchronize Customers
         */
        try {
            if ($helper->canAutoSyncCustomers() || $helper->getManualSync()) {
                if ($helper->getManualSync()) {
                    $helper->setManualSync(0);
                }

                /** @var $mailigen Mailigen_Synchronizer_Model_Mailigen */
                $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
                $mailigen->syncCustomers();
            }
        } catch (Exception $e) {
            Mage::helper('mailigen_synchronizer/log')->logException($e);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function adminSystemConfigChangedSectionMailigenSettings(Varien_Event_Observer $observer)
    {
        /** @var $list Mailigen_Synchronizer_Model_List */
        $list = Mage::getModel('mailigen_synchronizer/list');
        /** @var $config Mage_Core_Model_Config */
        $config = new Mage_Core_Model_Config();
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
        $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');
        $removeCache = false;

        /**
         * Create new newsletter list
         */
        $newsletterNewListName = Mage::getStoreConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_NEW_LIST_TITLE);
        if ($newsletterNewListName) {
            if ($mailigenSchedule->countPendingOrRunningJobs() == 0) {
                $newListValue = $list->createNewList($newsletterNewListName);
                if ($newListValue) {
                    $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_CONTACT_LIST, $newListValue, 'default', 0);
                    $removeCache = true;
                }
            }
            $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_NEW_LIST_TITLE, '', 'default', 0);
        }

        /**
         * Create new customers list
         */
        $customersNewListName = Mage::getStoreConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CUSTOMERS_NEW_LIST_TITLE);
        if ($customersNewListName) {
            if ($mailigenSchedule->countPendingOrRunningJobs() == 0) {
                $newListValue = $list->createNewList($customersNewListName);
                if ($newListValue) {
                    $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CUSTOMERS_CONTACT_LIST, $newListValue, 'default', 0);
                    $removeCache = true;

                    /**
                     * Set customers not synced on contact list change
                     */
                    /** @var $customer Mailigen_Synchronizer_Model_Customer */
                    $customer = Mage::getModel('mailigen_synchronizer/customer');
                    $customer->setCustomersNotSynced();
                }
            }
            $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CUSTOMERS_NEW_LIST_TITLE, '', 'default', 0);
        }

        /**
         * Check if user selected the same contact lists for newsletter and customers
         */
        if ($helper->getNewsletterContactList() == $helper->getCustomersContactList() && $helper->getNewsletterContactList() != '') {
            Mage::getSingleton('adminhtml/session')->addError("Please select different contact lists for newsletter and customers");
            $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CUSTOMERS_CONTACT_LIST, '', 'default', 0);
            $removeCache = true;
        }

        if ($removeCache) {
            $config->removeCache();
        }
    }

    /**
     * Add "Bulk synchronize with Mailigen" button "Manage Customers" page in BE
     *
     * @param Varien_Event_Observer $observer
     */
    public function adminhtmlWidgetContainerHtmlBefore(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_Customer && Mage::helper('mailigen_synchronizer')->isEnabled()) {
            $url = Mage::helper('adminhtml')->getUrl('*/mailigen/syncCustomers');
            $block->addButton('synchronize', array(
                'label' => Mage::helper('adminhtml')->__('Bulk synchronize with Mailigen'),
                'onclick' => "setLocation('{$url}')",
                'class' => 'task'
            ));
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerDeleteAfter(Varien_Event_Observer $observer)
    {
        $customer = $observer->getDataObject();
        if ($customer && $customer->getId()) {
            Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($customer->getId(), 1);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerSaveAfter(Varien_Event_Observer $observer)
    {
        $customer = $observer->getDataObject();
        if ($customer && $customer->getId()) {
            Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($customer->getId());

            /** @var $helper Mailigen_Synchronizer_Helper_Data */
            $helper = Mage::helper('mailigen_synchronizer');
            $newsletterListId = $helper->getNewsletterContactList();

            /**
             * Check if Customer Firstname, Lastname or Email was changed
             */
            if ($customer->getIsSubscribed() && $customer->hasDataChanges() && $helper->isEnabled() && !empty($newsletterListId)) {
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
                        Mage::getModel('mailigen_synchronizer/newsletter')->updateIsSynced($subscriber->getId(), false);
                    }
                }

                /**
                 * Unsubscribe with old email
                 */
                if ($emailChanged) {
                    $oldEmail = $origCustomerData['email'];
                    $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($oldEmail);

                    if ($subscriber->getId()) {
                        /** @var $logger Mailigen_Synchronizer_Helper_Log */
                        $logger = Mage::helper('mailigen_synchronizer/log');
                        $api = $helper->getMailigenApi();

                        /**
                         * Remove subscriber
                         */
                        $send_goodbye = $helper->canNewsletterHandleDefaultEmails();
                        $retval = $api->listUnsubscribe($newsletterListId, $oldEmail, true, $send_goodbye, true);
                        $logger->log('Remove subscriber with email: ' . $oldEmail);

                        if (!$retval) {
                            $logger->log("Unable to remove subscriber with email: $oldEmail. $api->errorCode: $api->errorMessage");
                        }
                    }
                }
            }
        }
    }
    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerAddressSaveAfter(Varien_Event_Observer $observer)
    {
        $customerAddress = $observer->getDataObject();
        $customer = $customerAddress->getCustomer();
        if ($customer && $customer->getId()) {
            Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($customer->getId());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function customerLogin(Varien_Event_Observer $observer)
    {
        $customer = $observer->getCustomer();
        if ($customer && $customer->getId()) {
            Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($customer->getId());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function salesOrderSaveAfter(Varien_Event_Observer $observer)
    {
        $order = $observer->getOrder();
        if ($order && $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE && $order->getCustomerId()) {
            Mage::getModel('mailigen_synchronizer/customer')->setCustomerNotSynced($order->getCustomerId());
        }
    }
}