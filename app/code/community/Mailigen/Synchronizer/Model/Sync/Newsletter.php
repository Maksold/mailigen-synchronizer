<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Sync_Newsletter extends Mailigen_Synchronizer_Model_Sync_Abstract
{
    public function doSync()
    {
        /**
         * Update subscribers in Mailigen
         */
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection')
            ->getSubscribers(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 0, $this->_storeId);
        if ($subscribers->getSize() > 0) {
            $this->l()->log("Started updating subscribers in Mailigen");
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
                $this->_logStats();
                $this->l()->log("Reschedule task, to update subscribers in Mailigen after 2 min");
                return;
            }

            $this->l()->log("Finished updating subscribers in Mailigen");
        } else {
            $this->l()->log("No subscribers to sync with Mailigen");
        }

        unset($subscribers);

        /**
         * Log subscribers info
         */
        $this->_logStats();

        /**
         * Update unsubscribers in Mailigen
         */
        /** @var $unsubscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $unsubscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection')
            ->getSubscribers(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 0, $this->_storeId);
        if ($unsubscribers->getSize() > 0) {
            $this->l()->log("Started updating unsubscribers in Mailigen");
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
                $this->_logStats();
                $this->l()->log("Reschedule task, to update unsubscribers in Mailigen after 2 min");
                return;
            }

            $this->l()->log("Finished updating unsubscribers in Mailigen");
        } else {
            $this->l()->log("No unsubscribers to sync with Mailigen");
        }

        unset($unsubscribers);
    }

    /**
     * @param $subscriber Mage_Newsletter_Model_Subscriber
     */
    public function _prepareSubscriberData($subscriber)
    {
        $this->_batchedData[$subscriber->getId()] = array(
            /**
             * Subscriber info
             */
            'EMAIL'          => $subscriber->getSubscriberEmail(),
            'FNAME'          => $subscriber->getCustomerFirstname(),
            'LNAME'          => $subscriber->getCustomerLastname(),
            'WEBSITEID'      => $subscriber->getWebsiteId(),
            'NEWSLETTERTYPE' => $this->customerHelper()->getSubscriberType($subscriber->getType()),
            'STOREID'        => $subscriber->getStoreId(),
            'STORELANGUAGE'  => $this->customerHelper()->getStoreLanguage($subscriber->getStoreId()),
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
        $api = $this->h()->getMailigenApi();
        $apiResponse = $api->listBatchSubscribe($this->_listId, $this->_batchedData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $this->l()->log("Updated $curr/$total subscribers in Mailigen");
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
             * Update Newsletter subscribers synced status
             */
            Mage::getModel('mailigen_synchronizer/newsletter')->updateSyncedNewsletter(array_keys($this->_batchedData));

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
     * @param $unsubscriber Mage_Newsletter_Model_Subscriber
     */
    public function _prepareUnsubscriberData($unsubscriber)
    {
        $this->_batchedData[$unsubscriber->getId()] = $unsubscriber->getSubscriberEmail();
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
        $api = $this->h()->getMailigenApi();
        $apiResponse = $api->listBatchUnsubscribe($this->_listId, $this->_batchedData, false, true);

        /**
         * Log results
         */
        if (isset($collectionInfo['currentPage']) && isset($collectionInfo['pageSize']) && isset($collectionInfo['pages'])) {
            $curr = $collectionInfo['currentPage'] * $collectionInfo['pageSize'];
            $total = $collectionInfo['pages'] * $collectionInfo['pageSize'];
            $this->l()->log("Updated $curr/$total unsubscribers in Mailigen");
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
             * Update Newsletter unsubscribers synced status
             */
            Mage::getModel('mailigen_synchronizer/newsletter')->updateSyncedNewsletter(array_keys($this->_batchedData));

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