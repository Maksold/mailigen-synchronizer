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
        $enabled = Mage::helper('mailigen_synchronizer')->isEnabled();
        $subscriber = $observer->getDataObject();
        $data = $subscriber->getData();
        $statusChange = $subscriber->getIsStatusChanged();

        if ($enabled && $statusChange == true) {

            $api = Mage::helper('mailigen_synchronizer')->getMailigenApi();
            $listid = Mage::helper('mailigen_synchronizer')->getNewsletterContactList();

            $email_address = $observer['subscriber']->getSubscriberEmail();
            $merge_vars = array('EMAIL' => $email_address); // or $merge_vars = array();
            $email_type = 'html';
            $double_optin = false;
            $update_existing = true;


            //If mailigen transational emails are set from admin.
            $send_flag = Mage::helper('mailigen_synchronizer')->canNewsletterHandleDefaultEmails();

            if ($send_flag) {
                $send_welcome = true;
                $send_goodbye = true;
            } else {
                $send_welcome = false;
                $send_goodbye = false;
            }

            //if is a customer we also grab firstname and lastname
            if ($observer['subscriber']->getCustomerId()) {
                $customer = Mage::getModel("customer/customer");
                $customer->load($observer['subscriber']->getCustomerId());

                $merge_vars['FNAME'] = $customer->getFirstname();
                $merge_vars['LNAME'] = $customer->getLastname();

            }

            Mage::log("Subscribe: " . $send_flag);

            if ($data['subscriber_status'] === 1) {
                $retval = $api->listSubscribe($listid, $email_address, $merge_vars, $email_type, $double_optin,
                    $update_existing, $send_welcome);
            } else {
                $retval = $api->listUnsubscribe($listid, $email_address, $delete_member, $send_goodbye, $send_notify);
            }


            if ($api->errorCode) {
                Mage::log("Mailigen API Error: " . "Code=" . $api->errorCode . " Msg=" . $api->errorMessage);
            } else {
                Mage::log("Returned: " . $retval);
            }
        }

        return $observer;
    }

    /**
     * Sync newsletter and customers by cron job
     */
    public function daily_sync()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

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
        $removeCache = false;

        /**
         * Create new newsletter list
         */
        $newsletterNewListName = Mage::getStoreConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_NEW_LIST_TITLE);
        if ($newsletterNewListName) {
            $newListValue = $list->createNewList($newsletterNewListName);
            if ($newListValue) {
                $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_CONTACT_LIST, $newListValue, 'default', 0);
                $removeCache = true;
            }
            $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_NEWSLETTER_NEW_LIST_TITLE, '', 'default', 0);
        }

        /**
         * Create new customers list
         */
        $customersNewListName = Mage::getStoreConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CUSTOMERS_NEW_LIST_TITLE);
        if ($customersNewListName) {
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
     * @param Varien_Event_Observer $observer
     */
    public function adminhtmlWidgetContainerHtmlBefore(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_Customer) {
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