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
    public function doSync()
    {
        /**
         * Update customers order info
         */
        $updatedCustomers = Mage::getModel('mailigen_synchronizer/customer')->updateCustomersOrderInfo($this->_storeId);
        $this->l()->log("Updated $updatedCustomers customers in flat table");


        /**
         * Update Customers in Mailigen
         */
        $updateCustomerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->getAllIds(0, 0, $this->_storeId);
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
                $this->_logStats();
                $this->l()->log("Reschedule task, to update customers in Mailigen after 2 min");
                return;
            }

            $this->l()->log("Finished updating subscribers in Mailigen");
        } else {
            $this->l()->log("No subscribers to sync with Mailigen");
        }

        unset($updateCustomerIds, $updateCustomers);

        /**
         * Log update info
         */
        $this->_logStats();


        /**
         * Remove Customers from Mailigen
         */
        /** @var $removeCustomer Mailigen_Synchronizer_Model_Resource_Customer_Collection */
        $removeCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->addFieldToFilter('is_removed', 1)
            ->addFieldToFilter('is_synced', 0)
            ->addFieldToFilter('store_id', $this->_storeId)
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
                $this->_logStats();
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
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     */
    public function _prepareCustomerDataForUpdate($customer)
    {
        $this->_batchedData[$customer->getId()] = array(

            /*
             * Basic fields
             */
            'WEBSITEID'                => $customer->getWebsiteId(),
            'STOREID'                  => $customer->getStoreId(),
            'STORELANGUAGE'            => $this->customerHelper()->getStoreLanguage($customer->getStoreId()),
            /*
             * Newsletter fields
             */
            'NEWSLETTERTYPE'           => $this->customerHelper()->getSubscriberType(Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_CUSTOMER_TYPE),
            /**
             * Customer info
             */
            'EMAIL'                    => $customer->getEmail(),
            'FNAME'                    => $customer->getFirstname(),
            'LNAME'                    => $customer->getLastname(),
            'PREFIX'                   => $customer->getPrefix(),
            'MIDDLENAME'               => $customer->getMiddlename(),
            'SUFFIX'                   => $customer->getSuffix(),
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

        $this->_stats['subscriber_count'] += count($this->_batchedData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_logStats();
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

            $this->_stats['subscriber_success_count'] += $apiResponse['success_count'];
            $this->_stats['subscriber_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_stats['subscriber_errors'] = array_merge_recursive($this->_stats['subscriber_errors'], $apiResponse['errors']);
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

        $this->_stats['unsubscriber_count'] += count($this->_batchedData);

        if ($api->errorCode) {
            /**
             * Reschedule job to run after 5 min
             */
            Mage::getModel('mailigen_synchronizer/schedule')->createJob(5);
            $this->_logStats();
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

            $this->_stats['unsubscriber_success_count'] += $apiResponse['success_count'];
            $this->_stats['unsubscriber_error_count'] += $apiResponse['error_count'];
            if (count($apiResponse['errors']) > 0) {
                $this->_stats['unsubscriber_errors'] = array_merge_recursive($this->_stats['unsubscriber_errors'], $apiResponse['errors']);
            }
        }

        /**
         * Check if sync should be stopped
         */
        $this->_checkSyncStop();

        $this->_batchedData = array();
    }
}