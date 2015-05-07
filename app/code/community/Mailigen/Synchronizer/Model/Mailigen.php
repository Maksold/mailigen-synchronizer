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

        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');

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
            $logger->log("Sync newsletter error:  Code={$api->errorCode} Msg={$api->errorMessage}");
        } else {
            Mage::getSingleton('adminhtml/session')->addSuccess("Your contacts have been syncronized");
            $logger->log("Sync newsletter success: " . var_export($retval, true));
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
            $logger->log("Started updating customers in Mailigen");
            $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $updateCustomers,
                array($this, '_prepareCustomerDataForUpdate'),
                array($this, '_updateCustomersInMailigen'),
                100,
                10000
            );
            /**
             * Reschedule task, to run after 2 min
             */
            if ($iterator == 0) {
                Mage::getModel('mailigen_synchronizer/schedule')->createJob(2);
                $this->_writeResultLogs();
                $logger->log("Reschedule task, to update customers in Mailigen after 2 min");
                return;
            }
            $logger->log("Finished updating customers in Mailigen");
        }
        unset($updateCustomerIds, $updateCustomers);

        /**
         * Log update info
         */
        $this->_writeResultLogs();


        /**
         * Remove Customers from Mailigen
         */
        /** @var $removeCustomer Mailigen_Synchronizer_Model_Resource_Customer_Collection */
        $removeCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->addFieldToFilter('is_removed', 1)
            ->addFieldToFilter('is_synced', 0)
            ->addFieldToSelect(array('id', 'email'));
        if ($removeCustomers && count($removeCustomers) > 0) {
            $logger->log("Started removing customers from Mailigen");
            $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $removeCustomers,
                array($this, '_prepareCustomerDataForRemove'),
                array($this, '_removeCustomersFromMailigen'),
                100,
                10000
            );
            /**
             * Reschedule task, to run after 2 min
             */
            if ($iterator == 0) {
                Mage::getModel('mailigen_synchronizer/schedule')->createJob(2);
                $this->_writeResultLogs();
                $logger->log("Reschedule task to remove customers in Mailigen after 2 min");
                return;
            }
            $logger->log("Finished removing customers from Mailigen");
        }
        unset($removeCustomers);

        /**
         * Remove synced and removed customers from Flat table
         */
        Mage::getModel('mailigen_synchronizer/customer')->removeSyncedAndRemovedCustomers();

        /**
         * Log remove info
         */
        $this->_writeResultLogs();

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

    /**
     * @param $collectionInfo
     */
    public function _updateCustomersInMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_customersListId, $this->_batchedCustomersData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $logger->log("Updated $curr/$total customers in Mailigen");
        }
        $this->_customersLog['update_count'] += count($this->_batchedCustomersData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode' => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse' => $apiResponse
            );
            Mage::throwException('Unable to batch unsubscribe. ' . var_export($errorInfo, true));
        } else {
            /**
             * Update Customer flat table
             */
            Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedCustomersData));

            $this->_customersLog['update_success_count'] += $apiResponse['success_count'];
            $this->_customersLog['update_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_customersLog['update_errors'] = array_merge_recursive($this->_customersLog['update_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedCustomersData = array();
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForRemove($customer)
    {
        $this->_batchedCustomersData[$customer->getId()] = $customer->getEmail();
    }

    /**
     * @param $collectionInfo
     */
    public function _removeCustomersFromMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_customersListId, $this->_batchedCustomersData, true, false, false);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $logger->log("Removed $curr/$total customers from Mailigen");
        }
        $this->_customersLog['remove_count'] = count($this->_batchedCustomersData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode' => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse' => $apiResponse
            );
            Mage::throwException('Unable to batch unsubscribe. ' . var_export($errorInfo, true));
        } else {
            /**
             * Update Customer flat table
             */
            Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedCustomersData));

            $this->_customersLog['remove_success_count'] += $apiResponse['success_count'];
            $this->_customersLog['remove_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_customersLog['remove_errors'] = array_merge_recursive($this->_customersLog['remove_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedCustomersData = array();
    }

    /**
     * Stop sync, if force sync stop is enabled
     */
    public function _checkSyncStop()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        if ($helper->getStopSync()) {
            $helper->setStopSync(0);

            /** @var $logger Mailigen_Synchronizer_Helper_Log */
            $logger = Mage::helper('mailigen_synchronizer/log');
            $logger->log('Sync has been stopped manually');
            die('Sync has been stopped manually');
        }
    }

    /**
     * Write update, remove result logs
     */
    protected function _writeResultLogs()
    {
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');

        if ($this->_customersLog['update_count'] > 0) {
            $logger->log("Successfully updated {$this->_customersLog['update_success_count']}/{$this->_customersLog['update_count']} customers");
            if (!empty($this->_customersLog['update_errors'])) {
                $logger->log("Update errors: " . var_export($this->_customersLog['update_errors'], true));
            }
        }

        if ($this->_customersLog['remove_count'] > 0) {
            $logger->log("Successfully removed {$this->_customersLog['remove_success_count']}/{$this->_customersLog['remove_count']} customers");
            $logger->log("Removed with error {$this->_customersLog['remove_error_count']}/{$this->_customersLog['remove_count']} customers");
            if (!empty($this->_customersLog['remove_errors'])) {
                $logger->log("Remove errors: " . var_export($this->_customersLog['remove_errors'], true));
            }
        }
    }
}