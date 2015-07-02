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
     * @var null
     */
    protected $_newsletterListId = null;

    /**
     * @var array
     */
    protected $_batchedCustomersData = array();

    /**
     * @var array
     */
    protected $_batchedNewsletterData = array();

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

    /**
     * @var array
     */
    protected $_newsletterLog = array(
        'subscriber_success_count' => 0,
        'subscriber_error_count' => 0,
        'subscriber_errors' => array(),
        'subscriber_count' => 0,
        'unsubscriber_success_count' => 0,
        'unsubscriber_error_count' => 0,
        'unsubscriber_errors' => array(),
        'unsubscriber_count' => 0,
    );

    public function syncNewsletter()
    {
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $logger->log('Newsletter synchronization started');
        $this->_newsletterListId = $helper->getNewsletterContactList();
        if (!$this->_newsletterListId) {
            Mage::throwException("Newsletter contact list isn't selected");
        }


        /**
         * Create or update Merge fields
         */
        Mage::getModel('mailigen_synchronizer/newsletter_merge_field')->createMergeFields();
        $logger->log('Newsletter merge fields created and updated');


        /**
         * Update subscribers in Mailigen
         */
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceSingleton('mailigen_synchronizer/subscriber_collection')
            ->getSubscribers(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
        if (count($subscribers) > 0) {
            $logger->log("Started updating subscribers in Mailigen");
            $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $subscribers,
                array($this, '_prepareSubscriberData'),
                array($this, '_updateSubscribersInMailigen'),
                100,
                10000
            );
            /**
             * Reschedule task, to run after 2 min
             */
            if ($iterator == 0) {
                Mage::getModel('mailigen_synchronizer/schedule')->createJob(2);
                $this->_writeResultLogs();
                $logger->log("Reschedule task, to update subscribers in Mailigen after 2 min");
                return;
            }
            $logger->log("Finished updating subscribers in Mailigen");
        }
        else {
            $logger->log("No subscribers to sync with Mailigen");
        }
        unset($subscribers);

        /**
         * Log subscribers info
         */
        $this->_writeResultLogs();

        /**
         * @todo Update unsubscribers in Mailigen
         */

        /**
         * Log unsubscribers info
         */
        $this->_writeResultLogs();

        $logger->log('Newsletter synchronization finished');
    }

    /**
     * @param $subscriber Mage_Newsletter_Model_Subscriber
     */
    public function _prepareSubscriberData($subscriber)
    {
        /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
        $customerHelper = Mage::helper('mailigen_synchronizer/customer');

        $this->_batchedNewsletterData[$subscriber->getId()] = array(
            /**
             * Subscriber info
             */
            'EMAIL' => $subscriber->getSubscriberEmail(),
            'FNAME' => $subscriber->getCustomerFirstname(),
            'LNAME' => $subscriber->getCustomerLastname(),
            'WEBSITEID' => $subscriber->getWebsiteId(),
            'TYPE' => $customerHelper->getSubscriberType($subscriber->getType()),
            'STOREID' => $subscriber->getStoreId(),
            'STORELANGUAGE' => $customerHelper->getStoreLanguage($subscriber->getStoreId()),
        );
    }

    /**
     * @param $collectionInfo
     */
    public function _updateSubscribersInMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_newsletterListId, $this->_batchedNewsletterData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $logger->log("Updated $curr/$total subscribers in Mailigen");
        }
        $this->_newsletterLog['subscriber_count'] += count($this->_batchedNewsletterData);

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
             * Update Newsletter subscribers synced status
             */
            Mage::getModel('mailigen_synchronizer/newsletter')->updateSyncedNewsletter(array_keys($this->_batchedNewsletterData));

            $this->_newsletterLog['subscriber_success_count'] += $apiResponse['success_count'];
            $this->_newsletterLog['subscriber_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_newsletterLog['subscriber_errors'] = array_merge_recursive($this->_newsletterLog['subscriber_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedNewsletterData = array();
    }

    /**
     * @param $unsubscriber Mage_Newsletter_Model_Subscriber
     */
    public function _prepareUnsubscriberData($unsubscriber)
    {
        $this->_batchedNewsletterData[$unsubscriber->getId()] = $unsubscriber->getSubscriberEmail();
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
        $logger->log('Customer merge fields created and updated');


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
            'ISSUBSCRIBED' => $helper->getFormattedIsSubscribed($customer->getData('is_subscribed')),
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

        /**
         * Newsletter logs
         */
        if (isset($this->_newsletterLog['subscriber_count']) && $this->_newsletterLog['subscriber_count'] > 0) {
            $logger->log("Successfully subscribed {$this->_newsletterLog['subscriber_success_count']}/{$this->_newsletterLog['subscriber_count']}");
            if (!empty($this->_newsletterLog['subscriber_errors'])) {
                $logger->log("Subscribe errors: " . var_export($this->_newsletterLog['subscriber_errors'], true));
            }
        }

        if (isset($this->_newsletterLog['unsubscriber_count']) && $this->_newsletterLog['unsubscriber_count'] > 0) {
            $logger->log("Successfully unsubscribed {$this->_newsletterLog['unsubscriber_success_count']}/{$this->_newsletterLog['unsubscriber_count']}");
            $logger->log("Unsubscribed with error {$this->_newsletterLog['unsubscriber_error_count']}/{$this->_newsletterLog['unsubscriber_count']}");
            if (!empty($this->_newsletterLog['unsubscriber_errors'])) {
                $logger->log("Unsubscribe errors: " . var_export($this->_newsletterLog['unsubscriber_errors'], true));
            }
        }

        /**
         * Customer logs
         */
        if (isset($this->_customersLog['update_count']) && $this->_customersLog['update_count'] > 0) {
            $logger->log("Successfully updated {$this->_customersLog['update_success_count']}/{$this->_customersLog['update_count']} customers");
            if (!empty($this->_customersLog['update_errors'])) {
                $logger->log("Update errors: " . var_export($this->_customersLog['update_errors'], true));
            }
        }

        if (isset($this->_customersLog['remove_count']) && $this->_customersLog['remove_count'] > 0) {
            $logger->log("Successfully removed {$this->_customersLog['remove_success_count']}/{$this->_customersLog['remove_count']} customers");
            $logger->log("Removed with error {$this->_customersLog['remove_error_count']}/{$this->_customersLog['remove_count']} customers");
            if (!empty($this->_customersLog['remove_errors'])) {
                $logger->log("Remove errors: " . var_export($this->_customersLog['remove_errors'], true));
            }
        }
    }
}