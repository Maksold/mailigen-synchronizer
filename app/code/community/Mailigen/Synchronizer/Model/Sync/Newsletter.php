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
    const SUBSCRIBER_TYPE = 'Guest';

    /**
     * @return Mailigen_Synchronizer_Model_Resource_Subscriber_Collection
     */
    protected function _getSubscribersCollection()
    {
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection');
        return $subscribers->getGuests(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 0, $this->_storeId);
    }

    /**
     * @return Mailigen_Synchronizer_Model_Resource_Subscriber_Collection
     */
    protected function _getUnsubscribersCollection()
    {
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection');
        return $subscribers->getGuests(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 0, $this->_storeId);
    }

    /**
     * @param $collectionInfo
     * @throws Mage_Core_Exception
     */
    public function _batchSubscribe($collectionInfo)
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
     * @param $collectionInfo
     * @throws Mage_Core_Exception
     */
    public function _batchUnsubscribe($collectionInfo)
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
            $this->l()->log("Unsubscribed $curr/$total guests from Mailigen");
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