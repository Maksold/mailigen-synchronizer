<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Mailigen extends Mage_Core_Model_Abstract
{
    /**
     * @var null
     */
    protected $_customersListId = null;

    /**
     * @var array
     */
    protected $_batchedCustomersData = array();

    /**
     * @var array
     */
    protected $_customersLog = array(
        'update_success_count' => 0,
        'update_error_count' => 0,
        'update_errors' => array(),
        'update_count' => 0,
        'remove_success_count' => 0,
        'remove_error_count' => 0,
        'remove_errors' => array(),
        'remove_count' => 0,
    );

    public function syncNewsletter()
    {
        $api = Mage::helper('mailigen_synchronizer')->getMailigenApi();
        $listid = Mage::helper('mailigen_synchronizer')->getNewsletterContactList();
        if (!$listid) {
            return;
        }

        //First we pull all unsubscribers from Mailigen
        $unsubscribers = $api->listMembers($listid, "unsubscribed", 0, 500);

        foreach ($unsubscribers as $unsubscriber) {

            $email = $unsubscriber['email'];

            // create new subscriber without send an confirmation email
            Mage::getModel('newsletter/subscriber')->setImportMode(true)->subscribe($email);

            // get just generated subscriber
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            // change status to "unsubscribed" and save
            $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
            $subscriber->save();
        }

        //Second we pull all subscribers from Mailigen
        $subscribers = $api->listMembers($listid, "subscribed", 0, 500);

        foreach ($subscribers as $subscriber) {

            $email = $subscriber['email'];


            // create new subscriber without send an confirmation email
            Mage::getModel('newsletter/subscriber')->setImportMode(true)->subscribe($email);

            // get just generated subscriber
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            // change status to "unsubscribed" and save
            $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
            $subscriber->save();
        }

        //and finally we push our list to mailigen
        $collection = Mage::getResourceSingleton('newsletter/subscriber_collection');
        $collection->showCustomerInfo(true)->addSubscriberTypeField()->showStoreInfo();

        $batch = array();
        foreach ($collection as $subscriber) {

            $batch[] = array(
                'EMAIL' => $subscriber->getSubscriberEmail(),
                'FNAME' => $subscriber->getCustomerFirstname(),
                'LNAME' => $subscriber->getCustomerLastname()
            );
        }

        $double_optin = false;
        $update_existing = true;
        $retval = $api->listBatchSubscribe($listid, $batch, $double_optin, $update_existing);

        if ($api->errorCode) {
            Mage::getSingleton('adminhtml/session')->addError("Something went wrong");
            Mage::log("Mailigen API Error: " . "Code=" . $api->errorCode . " Msg=" . $api->errorMessage);
        } else {
            Mage::getSingleton('adminhtml/session')->addSuccess("Your contacts have been syncronized");
            Mage::log("Returned: " . $retval);
        }
    }

    public function syncCustomers()
    {
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $logger->log('Customers synchronization started');
        $this->_customersListId = $helper->getCustomersContactList();
        if (!$this->_customersListId) {
            Mage::throwException("Customer contact list isn't selected");
        }


        /**
         * Create or update Merge fields
         */
        Mage::getModel('mailigen_synchronizer/customer_merge_field')->createMergeFields();
        $logger->log('Merge fields created and updated');


        /**
         * Update customers order info
         */
        $updatedCustomers = Mage::getModel('mailigen_synchronizer/customer')->updateCustomersOrderInfo();
        $logger->log("Updated $updatedCustomers customers in flat table");


        /**
         * Update Customers in Mailigen
         */
        $updateCustomerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()->getAllIds(0, 0);
        /** @var $updateCustomers Mage_Customer_Model_Resource_Customer_Collection */
        $updateCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCustomerCollection($updateCustomerIds);
        if (count($updateCustomerIds) > 0 && $updateCustomers) {
            Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $updateCustomers,
                array($this, '_prepareCustomerDataForUpdate'),
                array($this, '_updateCustomersInMailigen'),
                200
            );
        }
        $this->_customersLog['update_count'] = count($updateCustomerIds);
        unset($updateCustomerIds, $updateCustomers);

        /**
         * Log update info
         */
        $logger->log("Successfully updated {$this->_customersLog['update_success_count']}/{$this->_customersLog['update_count']} customers");
        $logger->log("Updated with error {$this->_customersLog['update_error_count']}/{$this->_customersLog['update_count']} customers");
        if (!empty($this->_customersLog['update_errors'])) {
            $logger->log("Update errors: " . var_export($this->_customersLog['update_errors'], true));
        }


        /**
         * Remove Customers from Mailigen
         */
        $removeCustomerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()->getAllIds(0, 1);
        /** @var $removeCustomers Mage_Customer_Model_Resource_Customer_Collection */
        $removeCustomers = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('email')
            ->addAttributeToFilter('entity_id', array('in' => $removeCustomerIds));
        if (count($removeCustomerIds) > 0 && $removeCustomers) {
            Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $removeCustomers,
                array($this, '_prepareCustomerDataForRemove'),
                array($this, '_removeCustomersFromMailigen'),
                200
            );
        }
        $this->_customersLog['remove_count'] = count($removeCustomerIds);
        unset($removeCustomerIds, $removeCustomers);

        /**
         * Remove synced and removed customers from Flat table
         */
        Mage::getModel('mailigen_synchronizer/customer')->removeSyncedAndRemovedCustomers();

        /**
         * Log remove info
         */
        $logger->log("Successfully removed {$this->_customersLog['remove_success_count']}/{$this->_customersLog['remove_count']} customers");
        $logger->log("Removed with error {$this->_customersLog['remove_error_count']}/{$this->_customersLog['remove_count']} customers");
        if (!empty($this->_customersLog['remove_errors'])) {
            $logger->log("Remove errors: " . var_export($this->_customersLog['remove_errors'], true));
        }

        $logger->log('Customers synchronization finished');
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForUpdate($customer)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Customer */
        $helper = Mage::helper('mailigen_synchronizer/customer');

        $this->_batchedCustomersData[$customer->getId()] = array(
            /**
             * Customer info
             */
            'EMAIL' => $customer->getEmail(),
            'FNAME' => $customer->getFirstname(),
            'LNAME' => $customer->getLastname(),
            'PREFIX' => $customer->getPrefix(),
            'MIDDLENAME' => $customer->getMiddlename(),
            'SUFFIX' => $customer->getSuffix(),
            'STOREID' => $customer->getStoreId(),
            'STORELANGUAGE' => $helper->getStoreLanguage($customer->getStoreId()),
            'CUSTOMERGROUP' => $helper->getCustomerGroup($customer->getGroupId()),
            'PHONE' => $customer->getBillingTelephone(),
            'REGISTRATIONDATE' => $helper->getFormattedDate($customer->getCreatedAtTimestamp()),
            'COUNTRY' => $helper->getFormattedCountry($customer->getBillingCountryId()),
            'CITY' => $customer->getBillingCity(),
            'DATEOFBIRTH' => $helper->getFormattedDate($customer->getDob()),
            'GENDER' => $helper->getFormattedGender($customer->getGender()),
            'LASTLOGIN' => $helper->getFormattedDate($customer->getLastLoginAt()),
            'CLIENTID' => $customer->getId(),
            'STATUSOFUSER' => $helper->getFormattedCustomerStatus($customer->getIsActive()),
            /**
             * Customer orders info
             */
            'LASTORDERDATE' => $customer->getData('lastorderdate'),
            'VALUEOFLASTORDER' => $customer->getData('valueoflastorder'),
            'TOTALVALUEOFORDERS' => $customer->getData('totalvalueoforders'),
            'TOTALNUMBEROFORDERS' => $customer->getData('totalnumberoforders'),
            'NUMBEROFITEMSINCART' => $customer->getData('numberofitemsincart'),
            'VALUEOFCURRENTCART' => $customer->getData('valueofcurrentcart'),
            'LASTITEMINCARTADDINGDATE' => $customer->getData('lastitemincartaddingdate')
        );
    }

    public function _updateCustomersInMailigen()
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_customersListId, $this->_batchedCustomersData, false, true);

        /**
         * Update Customer flat table
         */
        Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedCustomersData));

        /**
         * Log results
         */
        if ($api->errorCode) {
            Mage::throwException("Unable to batch subscribe. $api->errorCode: $api->errorMessage");
        } else {
            $this->_customersLog['update_success_count'] += $apiResponse['success_count'];
            $this->_customersLog['update_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_customersLog['update_errors'] = array_merge_recursive($this->_customersLog['update_errors'], $apiResponse['errors']);
            }
        }

        $this->_batchedCustomersData = array();
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForRemove($customer)
    {
        $this->_batchedCustomersData[$customer->getId()] = $customer->getEmail();
    }

    public function _removeCustomersFromMailigen()
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_customersListId, $this->_batchedCustomersData, true, false, false);

        /**
         * Update Customer flat table
         */
        Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedCustomersData));

        /**
         * Log results
         */
        if ($api->errorCode) {
            Mage::throwException("Unable to batch unsubscribe. $api->errorCode: $api->errorMessage");
        } else {
            $this->_customersLog['remove_success_count'] += $apiResponse['success_count'];
            $this->_customersLog['remove_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_customersLog['remove_errors'] = array_merge_recursive($this->_customersLog['remove_errors'], $apiResponse['errors']);
            }
        }

        $this->_batchedCustomersData = array();
    }
}