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
    protected $_customersListId;

    /**
     * @var null
     */
    protected $_newsletterListId;

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
    protected $_customersLog = array();

    /**
     * @var array
     */
    protected $_newsletterLog = array();

    protected function _resetNewsletterLog()
    {
        $this->_newsletterLog = array(
            'subscriber_success_count'   => 0,
            'subscriber_error_count'     => 0,
            'subscriber_errors'          => array(),
            'subscriber_count'           => 0,
            'unsubscriber_success_count' => 0,
            'unsubscriber_error_count'   => 0,
            'unsubscriber_errors'        => array(),
            'unsubscriber_count'         => 0,
        );
    }

    protected function _resetCustomerLog()
    {
        $this->_customersLog = array(
            'update_success_count' => 0,
            'update_error_count'   => 0,
            'update_errors'        => array(),
            'update_count'         => 0,
            'remove_success_count' => 0,
            'remove_error_count'   => 0,
            'remove_errors'        => array(),
            'remove_count'         => 0,
        );
    }

    public function syncNewsletter()
    {
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $log->setLogFile(Mailigen_Synchronizer_Helper_Log::SYNC_LOG_FILE);
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $emulation Mage_Core_Model_App_Emulation */
        $emulation = Mage::getModel('core/app_emulation');

        /**
         * Get Newsletter lists per store
         */
        $newsletterLists = $helper->getNewsletterContactLists();
        if (count($newsletterLists) <= 0) {
            $log->log("Newsletter contact list isn't selected");
            return;
        }

        try {

            $log->log('Newsletter synchronization started for Store Ids: ' . implode(', ', array_keys($newsletterLists)));

            foreach ($newsletterLists as $_storeId => $newsletterListId) {
                $log->log('Newsletter synchronization started for Store Id: ' . $_storeId);

                $environment = $emulation->startEnvironmentEmulation($_storeId);
                $this->_newsletterListId = $newsletterListId;
                $this->_resetNewsletterLog();


                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/newsletter_merge_field')->createMergeFields();
                $log->log('Newsletter merge fields created and updated');


                /**
                 * Update subscribers in Mailigen
                 */
                /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
                $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection')
                    ->getSubscribers(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 0, $_storeId);
                if ($subscribers->getSize() > 0) {
                    $log->log("Started updating subscribers in Mailigen");
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
                        $log->log("Reschedule task, to update subscribers in Mailigen after 2 min");
                        return;
                    }

                    $log->log("Finished updating subscribers in Mailigen");
                } else {
                    $log->log("No subscribers to sync with Mailigen");
                }

                unset($subscribers);

                /**
                 * Log subscribers info
                 */
                $this->_writeResultLogs();

                /**
                 * Update unsubscribers in Mailigen
                 */
                /** @var $unsubscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
                $unsubscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection')
                    ->getSubscribers(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 0, $_storeId);
                if ($unsubscribers->getSize() > 0) {
                    $log->log("Started updating unsubscribers in Mailigen");
                    $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                        $unsubscribers,
                        array($this, '_prepareUnsubscriberData'),
                        array($this, '_updateUnsubscribersInMailigen'),
                        100,
                        10000
                    );
                    /**
                     * Reschedule task, to run after 2 min
                     */
                    if ($iterator == 0) {
                        Mage::getModel('mailigen_synchronizer/schedule')->createJob(2);
                        $this->_writeResultLogs();
                        $log->log("Reschedule task, to update unsubscribers in Mailigen after 2 min");
                        return;
                    }

                    $log->log("Finished updating unsubscribers in Mailigen");
                } else {
                    $log->log("No unsubscribers to sync with Mailigen");
                }

                unset($unsubscribers);

                /**
                 * Log unsubscribers info
                 */
                $this->_writeResultLogs();

                $emulation->stopEnvironmentEmulation($environment);

                $log->log('Newsletter synchronization finished for Store Id: ' . $_storeId);
            }

            $log->log('Newsletter synchronization finished for Store Ids: ' . implode(', ', array_keys($newsletterLists)));

        } catch (Exception $e) {
            $log->logException($e);
        }
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
            'EMAIL'         => $subscriber->getSubscriberEmail(),
            'FNAME'         => $subscriber->getCustomerFirstname(),
            'LNAME'         => $subscriber->getCustomerLastname(),
            'WEBSITEID'     => $subscriber->getWebsiteId(),
            'TYPE'          => $customerHelper->getSubscriberType($subscriber->getType()),
            'STOREID'       => $subscriber->getStoreId(),
            'STORELANGUAGE' => $customerHelper->getStoreLanguage($subscriber->getStoreId()),
        );
    }

    /**
     * @param $collectionInfo
     * @throws Mage_Core_Exception
     */
    public function _updateSubscribersInMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_newsletterListId, $this->_batchedNewsletterData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $log->log("Updated $curr/$total subscribers in Mailigen");
        }

        $this->_newsletterLog['subscriber_count'] += count($this->_batchedNewsletterData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode'    => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse'  => $apiResponse,
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
     * @param $collectionInfo
     * @throws Mage_Core_Exception
     */
    public function _updateUnsubscribersInMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_newsletterListId, $this->_batchedNewsletterData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $log->log("Updated $curr/$total unsubscribers in Mailigen");
        }

        $this->_newsletterLog['unsubscriber_count'] += count($this->_batchedNewsletterData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode'    => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse'  => $apiResponse,
            );
            Mage::throwException('Unable to batch unsubscribe. ' . var_export($errorInfo, true));
        } else {
            /**
             * Update Newsletter unsubscribers synced status
             */
            Mage::getModel('mailigen_synchronizer/newsletter')->updateSyncedNewsletter(array_keys($this->_batchedNewsletterData));

            $this->_newsletterLog['unsubscriber_success_count'] += $apiResponse['success_count'];
            $this->_newsletterLog['unsubscriber_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_newsletterLog['unsubscriber_errors'] = array_merge_recursive($this->_newsletterLog['unsubscriber_errors'], $apiResponse['errors']);
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
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $log->setLogFile(Mailigen_Synchronizer_Helper_Log::SYNC_LOG_FILE);
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
        $customerHelper = Mage::helper('mailigen_synchronizer/customer');
        /** @var $emulation Mage_Core_Model_App_Emulation */
        $emulation = Mage::getModel('core/app_emulation');

        /**
         * Get Customer lists per store
         */
        $customerLists = $helper->getCustomerContactLists();
        if (count($customerLists) <= 0) {
            $log->log("Customer contact list isn't selected");
            return;
        }

        try {

            $log->log('Customer synchronization started for Store Ids: ' . implode(', ', array_keys($customerLists)));

            foreach ($customerLists as $_storeId => $customerListId) {
                $log->log('Customer synchronization started for Store Id: ' . $_storeId);

                $environment = $emulation->startEnvironmentEmulation($_storeId);
                $this->_customersListId = $customerListId;
                $this->_resetCustomerLog();


                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/customer_merge_field')->createMergeFields();
                $log->log('Customer merge fields created and updated');


                /**
                 * Update customers order info
                 */
                $updatedCustomers = Mage::getModel('mailigen_synchronizer/customer')->updateCustomersOrderInfo($_storeId);
                $log->log("Updated $updatedCustomers customers in flat table");


                /**
                 * Update Customers in Mailigen
                 */
                $updateCustomerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
                    ->getAllIds(0, 0, $_storeId);
                /** @var $updateCustomers Mage_Customer_Model_Resource_Customer_Collection */
                $updateCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCustomerCollection($updateCustomerIds);
                if (count($updateCustomerIds) > 0 && $updateCustomers) {
                    $log->log("Started updating customers in Mailigen");
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
                        $log->log("Reschedule task, to update customers in Mailigen after 2 min");
                        return;
                    }

                    $log->log("Finished updating customers in Mailigen");
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
                    ->addFieldToFilter('store_id', $_storeId)
                    ->addFieldToSelect(array('id', 'email'));
                if ($removeCustomers && $removeCustomers->getSize() > 0) {
                    $log->log("Started removing customers from Mailigen");
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
                        $log->log("Reschedule task to remove customers in Mailigen after 2 min");
                        return;
                    }

                    $log->log("Finished removing customers from Mailigen");
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

                $emulation->stopEnvironmentEmulation($environment);

                $log->log('Customer synchronization finished for Store Id: ' . $_storeId);
            }

            $log->log('Customer synchronization finished for Store Ids: ' . implode(', ', array_keys($customerLists)));

        } catch (Exception $e) {
            $log->logException($e);
        }
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
            'EMAIL'                    => $customer->getEmail(),
            'FNAME'                    => $customer->getFirstname(),
            'LNAME'                    => $customer->getLastname(),
            'PREFIX'                   => $customer->getPrefix(),
            'MIDDLENAME'               => $customer->getMiddlename(),
            'SUFFIX'                   => $customer->getSuffix(),
            'STOREID'                  => $customer->getStoreId(),
            'STORELANGUAGE'            => $helper->getStoreLanguage($customer->getStoreId()),
            'CUSTOMERGROUP'            => $helper->getCustomerGroup($customer->getGroupId()),
            'PHONE'                    => $customer->getBillingTelephone(),
            'REGISTRATIONDATE'         => $helper->getFormattedDate($customer->getCreatedAtTimestamp()),
            'COUNTRY'                  => $helper->getFormattedCountry($customer->getBillingCountryId()),
            'CITY'                     => $customer->getBillingCity(),
            'REGION'                   => $helper->getFormattedRegion($customer->getBillingRegionId()),
            'DATEOFBIRTH'              => $helper->getFormattedDate($customer->getDob()),
            'GENDER'                   => $helper->getFormattedGender($customer->getGender()),
            'LASTLOGIN'                => $helper->getFormattedDate($customer->getLastLoginAt()),
            'CLIENTID'                 => $customer->getId(),
            'STATUSOFUSER'             => $helper->getFormattedCustomerStatus($customer->getIsActive()),
            'ISSUBSCRIBED'             => $helper->getFormattedIsSubscribed($customer->getData('is_subscribed')),
            /**
             * Customer orders info
             */
            'LASTORDERDATE'            => $customer->getData('lastorderdate'),
            'VALUEOFLASTORDER'         => $customer->getData('valueoflastorder'),
            'TOTALVALUEOFORDERS'       => $customer->getData('totalvalueoforders'),
            'TOTALNUMBEROFORDERS'      => $customer->getData('totalnumberoforders'),
            'NUMBEROFITEMSINCART'      => $customer->getData('numberofitemsincart'),
            'VALUEOFCURRENTCART'       => $customer->getData('valueofcurrentcart'),
            'LASTITEMINCARTADDINGDATE' => $customer->getData('lastitemincartaddingdate'),
        );
    }

    /**
     * @param $collectionInfo
     * @throws Mage_Core_Exception
     */
    public function _updateCustomersInMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_customersListId, $this->_batchedCustomersData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $log->log("Updated $curr/$total customers in Mailigen");
        }

        $this->_customersLog['update_count'] += count($this->_batchedCustomersData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode'    => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse'  => $apiResponse,
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
     * @throws Mage_Core_Exception
     */
    public function _removeCustomersFromMailigen($collectionInfo)
    {
        /**
         * Send API request to Mailigen
         */
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');
        $api = $helper->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_customersListId, $this->_batchedCustomersData, true, false, false);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $log->log("Removed $curr/$total customers from Mailigen");
        }

        $this->_customersLog['remove_count'] = count($this->_batchedCustomersData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_writeResultLogs();
            $errorInfo = array(
                'errorCode'    => $api->errorCode,
                'errorMessage' => $api->errorMessage,
                'apiResponse'  => $apiResponse,
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
            Mage::throwException('Sync has been stopped manually');
        }
    }

    /**
     * Write update, remove result logs
     */
    protected function _writeResultLogs()
    {
        /** @var $log Mailigen_Synchronizer_Helper_Log */
        $log = Mage::helper('mailigen_synchronizer/log');

        /**
         * Newsletter logs
         */
        if (isset($this->_newsletterLog['subscriber_count']) && $this->_newsletterLog['subscriber_count'] > 0) {
            $log->log("Successfully subscribed {$this->_newsletterLog['subscriber_success_count']}/{$this->_newsletterLog['subscriber_count']}");
            if (!empty($this->_newsletterLog['subscriber_errors'])) {
                $log->log("Subscribe errors: " . var_export($this->_newsletterLog['subscriber_errors'], true));
            }
        }

        if (isset($this->_newsletterLog['unsubscriber_count']) && $this->_newsletterLog['unsubscriber_count'] > 0) {
            $log->log("Successfully unsubscribed {$this->_newsletterLog['unsubscriber_success_count']}/{$this->_newsletterLog['unsubscriber_count']}");
            $log->log("Unsubscribed with error {$this->_newsletterLog['unsubscriber_error_count']}/{$this->_newsletterLog['unsubscriber_count']}");
            if (!empty($this->_newsletterLog['unsubscriber_errors'])) {
                $log->log("Unsubscribe errors: " . var_export($this->_newsletterLog['unsubscriber_errors'], true));
            }
        }

        /**
         * Customer logs
         */
        if (isset($this->_customersLog['update_count']) && $this->_customersLog['update_count'] > 0) {
            $log->log("Successfully updated {$this->_customersLog['update_success_count']}/{$this->_customersLog['update_count']} customers");
            if (!empty($this->_customersLog['update_errors'])) {
                $log->log("Update errors: " . var_export($this->_customersLog['update_errors'], true));
            }
        }

        if (isset($this->_customersLog['remove_count']) && $this->_customersLog['remove_count'] > 0) {
            $log->log("Successfully removed {$this->_customersLog['remove_success_count']}/{$this->_customersLog['remove_count']} customers");
            $log->log("Removed with error {$this->_customersLog['remove_error_count']}/{$this->_customersLog['remove_count']} customers");
            if (!empty($this->_customersLog['remove_errors'])) {
                $log->log("Remove errors: " . var_export($this->_customersLog['remove_errors'], true));
            }
        }
    }
}