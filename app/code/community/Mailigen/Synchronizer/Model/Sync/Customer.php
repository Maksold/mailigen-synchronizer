<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Sync_Customer extends Mailigen_Synchronizer_Model_Sync_Abstract
{
    protected function _resetStats()
    {
        $this->_stats = array(
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

    public function sync()
    {
        /** @var $emulation Mage_Core_Model_App_Emulation */
        $emulation = Mage::getModel('core/app_emulation');

        /**
         * Get Customer lists per store
         */
        $customerLists = $this->h()->getCustomerContactLists();
        if (count($customerLists) <= 0) {
            $this->l()->log("Customer contact list isn't selected");
            return;
        }

        try {

            $this->l()->log('Customer synchronization started for Store Ids: ' . implode(', ', array_keys($customerLists)));

            foreach ($customerLists as $_storeId => $customerListId) {
                $this->l()->log('Customer synchronization started for Store Id: ' . $_storeId);

                $environment = $emulation->startEnvironmentEmulation($_storeId);
                $this->_listId = $customerListId;
                $this->_resetStats();


                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/merge_field_customer')
                    ->setStoreId($_storeId)
                    ->createMergeFields();
                $this->l()->log('Customer merge fields created and updated');


                /**
                 * Update customers order info
                 */
                $updatedCustomers = Mage::getModel('mailigen_synchronizer/customer')->updateCustomersOrderInfo($_storeId);
                $this->l()->log("Updated $updatedCustomers customers in flat table");


                /**
                 * Update Customers in Mailigen
                 */
                $updateCustomerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
                    ->getAllIds(0, 0, $_storeId);
                /** @var $updateCustomers Mage_Customer_Model_Resource_Customer_Collection */
                $updateCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCustomerCollection($updateCustomerIds);
                if (count($updateCustomerIds) > 0 && $updateCustomers) {
                    $this->l()->log("Started updating customers in Mailigen");
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
                        $this->l()->log("Reschedule task, to update customers in Mailigen after 2 min");
                        return;
                    }

                    $this->l()->log("Finished updating customers in Mailigen");
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
                    $this->l()->log("Started removing customers from Mailigen");
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
                        $this->l()->log("Reschedule task to remove customers in Mailigen after 2 min");
                        return;
                    }

                    $this->l()->log("Finished removing customers from Mailigen");
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

                $this->l()->log('Customer synchronization finished for Store Id: ' . $_storeId);
            }

            $this->l()->log('Customer synchronization finished for Store Ids: ' . implode(', ', array_keys($customerLists)));

        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForUpdate($customer)
    {
        $this->_batchedData[$customer->getId()] = array(
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
            'STORELANGUAGE'            => $this->customerHelper()->getStoreLanguage($customer->getStoreId()),
            'CUSTOMERGROUP'            => $this->customerHelper()->getCustomerGroup($customer->getGroupId()),
            'PHONE'                    => $customer->getBillingTelephone(),
            'REGISTRATIONDATE'         => $this->customerHelper()->getFormattedDate($customer->getCreatedAtTimestamp()),
            'COUNTRY'                  => $this->customerHelper()->getFormattedCountry($customer->getBillingCountryId()),
            'CITY'                     => $customer->getBillingCity(),
            'REGION'                   => $this->customerHelper()->getFormattedRegion($customer->getBillingRegionId()),
            'DATEOFBIRTH'              => $this->customerHelper()->getFormattedDate($customer->getDob()),
            'GENDER'                   => $this->customerHelper()->getFormattedGender($customer->getGender()),
            'LASTLOGIN'                => $this->customerHelper()->getFormattedDate($customer->getLastLoginAt()),
            'CLIENTID'                 => $customer->getId(),
            'STATUSOFUSER'             => $this->customerHelper()->getFormattedCustomerStatus($customer->getIsActive()),
            'ISSUBSCRIBED'             => $this->customerHelper()->getFormattedIsSubscribed($customer->getData('is_subscribed')),
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
        $api = $this->h()->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_listId, $this->_batchedData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $this->l()->log("Updated $curr/$total customers in Mailigen");
        }

        $this->_stats['update_count'] += count($this->_batchedData);

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
            Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedData));

            $this->_stats['update_success_count'] += $apiResponse['success_count'];
            $this->_stats['update_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_stats['update_errors'] = array_merge_recursive($this->_stats['update_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedData = array();
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForRemove($customer)
    {
        $this->_batchedData[$customer->getId()] = $customer->getEmail();
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
        $api = $this->h()->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_listId, $this->_batchedData, true, false, false);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $this->l()->log("Removed $curr/$total customers from Mailigen");
        }

        $this->_stats['remove_count'] = count($this->_batchedData);

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
            Mage::getModel('mailigen_synchronizer/customer')->updateSyncedCustomers(array_keys($this->_batchedData));

            $this->_stats['remove_success_count'] += $apiResponse['success_count'];
            $this->_stats['remove_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_stats['remove_errors'] = array_merge_recursive($this->_stats['remove_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedData = array();
    }

    /**
     * Write update, remove result logs
     */
    protected function _writeResultLogs()
    {
        if (isset($this->_stats['update_count']) && $this->_stats['update_count'] > 0) {
            $this->l()->log("Successfully updated {$this->_stats['update_success_count']}/{$this->_stats['update_count']} customers");
            if (!empty($this->_stats['update_errors'])) {
                $this->l()->log("Update errors: " . var_export($this->_stats['update_errors'], true));
            }
        }

        if (isset($this->_stats['remove_count']) && $this->_stats['remove_count'] > 0) {
            $this->l()->log("Successfully removed {$this->_stats['remove_success_count']}/{$this->_stats['remove_count']} customers");
            $this->l()->log("Removed with error {$this->_stats['remove_error_count']}/{$this->_stats['remove_count']} customers");
            if (!empty($this->_stats['remove_errors'])) {
                $this->l()->log("Remove errors: " . var_export($this->_stats['remove_errors'], true));
            }
        }
    }
}