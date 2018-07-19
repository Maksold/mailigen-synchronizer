<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
abstract class Mailigen_Synchronizer_Model_Sync_Abstract
{
    const SUBSCRIBER_TYPE = '';

    /**
     * @var null|string
     */
    protected $_listId;

    /**
     * @var null|int
     */
    protected $_storeId;

    /**
     * @var array
     */
    protected $_batchedData = array();

    /**
     * @var array
     */
    protected $_stats = array();

    public function __construct()
    {
        $this->l()->setLogFile(Mailigen_Synchronizer_Helper_Log::SYNC_LOG_FILE);
    }

    protected function _resetStats()
    {
        $this->_stats = array(
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

    public function sync()
    {
        /** @var $emulation Mage_Core_Model_App_Emulation */
        $emulation = Mage::getModel('core/app_emulation');

        /**
         * Get Mailigen Contact lists per store
         */
        $mailigenLists = $this->h()->getCustomerContactLists();
        if (count($mailigenLists) <= 0) {
            $this->l()->log('Mailigen contact list isn\'t selected');
            return;
        }

        try {

            $this->l()->log($this->l()->__('%s sync started for Store Ids: %s',
                static::SUBSCRIBER_TYPE, implode(', ', array_keys($mailigenLists))
            ));

            foreach ($mailigenLists as $_storeId => $_listId) {
                $this->_storeId = (int)$_storeId;
                $this->_listId = $_listId;
                $this->l()->log($this->l()->__('%s sync started for Store Id: %s',
                    static::SUBSCRIBER_TYPE, $this->_storeId
                ));

                $environment = $emulation->startEnvironmentEmulation($this->_storeId);
                $this->_resetStats();

                /**
                 * 1. Create/update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/merge_field_customer')
                    ->setStoreId($this->_storeId)
                    ->createMergeFields();
                $this->l()->log('Merge fields created and updated');

                /**
                 * 2. Subscribe
                 */
                $this->_beforeSubscribe();
                $this->_subscribe();
                $this->_afterSubscribe();

                /**
                 * 3. Unsubscribe
                 */
                $this->_beforeUnsubscribe();
                $this->_unsubscribe();
                $this->_afterUnsubscribe();

                $emulation->stopEnvironmentEmulation($environment);


                $this->l()->log($this->l()->__('%s sync finished for Store Id: %s',
                    static::SUBSCRIBER_TYPE, $this->_storeId
                ));
                $this->_storeId = null;
                $this->_listId = null;
            }

            $this->l()->log($this->l()->__('%s sync finished for Store Ids: %s',
                static::SUBSCRIBER_TYPE, implode(', ', array_keys($mailigenLists))
            ));

        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    abstract protected function _getSubscribersCollection();

    protected function _subscribe()
    {
        $subscribers = $this->_getSubscribersCollection();

        if ($subscribers && $subscribers->getSize() > 0) {
            $this->l()->log($this->l()->__('Started %s subscribe', static::SUBSCRIBER_TYPE));
            $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $subscribers,
                array($this, '_prepareBatchSubscribeData'),
                array($this, '_batchSubscribe'),
                100,
                10000
            );

            /**
             * Reschedule task, to run after 2 min
             */
            if ($iterator == 0) {
                $minutes = 2;
                Mage::getModel('mailigen_synchronizer/schedule')->createJob($minutes);
                $this->_logStats();
                $this->l()->log($this->l()->__('Reschedule task, to subscribe %s after %s min.', static::SUBSCRIBER_TYPE, $minutes));
                return;
            }

            $this->l()->log($this->l()->__('Finished %s subscribe', static::SUBSCRIBER_TYPE));
        } else {
            $this->l()->log($this->l()->__('No %s to subscribe', static::SUBSCRIBER_TYPE));
        }
    }

    protected function _beforeSubscribe()
    {
    }

    protected function _afterSubscribe()
    {
        $this->_logStats();
    }

    abstract protected function _getUnsubscribersCollection();

    protected function _unsubscribe()
    {
        $unsubscribers = $this->_getUnsubscribersCollection();

        if ($unsubscribers && $unsubscribers->getSize() > 0) {
            $this->l()->log($this->l()->__('Started %s unsubscribe', static::SUBSCRIBER_TYPE));
            $iterator = Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $unsubscribers,
                array($this, '_prepareBatchUnsubscribeData'),
                array($this, '_batchUnsubscribe'),
                100,
                10000
            );

            /**
             * Reschedule task, to run after 2 min
             */
            if ($iterator == 0) {
                $minutes = 2;
                Mage::getModel('mailigen_synchronizer/schedule')->createJob($minutes);
                $this->_logStats();
                $this->l()->log($this->l()->__('Reschedule task, to unsubscribe %s after %s min.', static::SUBSCRIBER_TYPE, $minutes));
                return;
            }

            $this->l()->log($this->l()->__('Finished %s unsubscribe', static::SUBSCRIBER_TYPE));
        } else {
            $this->l()->log($this->l()->__('No %s to unsubscribe', static::SUBSCRIBER_TYPE));
        }
    }

    protected function _beforeUnsubscribe()
    {
    }

    protected function _afterUnsubscribe()
    {
        $this->_logStats();
    }

    /**
     * @param Mage_Customer_Model_Customer|Mage_Newsletter_Model_Subscriber $subscriber
     */
    public function _prepareBatchSubscribeData($subscriber)
    {
        /*
         * Basic fields
         */
        $_subscriberType = $subscriber->getType() ? $subscriber->getType() : Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_CUSTOMER_TYPE;
        $basicFields = array(
            'EMAIL'          => $subscriber->getEmail(),
            'NEWSLETTERTYPE' => $this->customerHelper()->getSubscriberType($_subscriberType),
            'WEBSITEID'      => $subscriber->getWebsiteId(),
            'STOREID'        => $subscriber->getStoreId(),
            'STORELANGUAGE'  => $this->customerHelper()->getStoreLanguage($subscriber->getStoreId()),
        );

        $this->_batchedData[$subscriber->getId()] = $basicFields;
    }

    /**
     * @param $unsubscriber Mage_Customer_Model_Customer|Mage_Newsletter_Model_Subscriber
     */
    public function _prepareBatchUnsubscribeData($unsubscriber)
    {
        $this->_batchedData[$unsubscriber->getId()] = $unsubscriber->getEmail();
    }

    /**
     * Stop sync, if force sync stop is enabled
     */
    protected function _checkSyncStop()
    {
        if ($this->h()->getStopSync()) {
            $this->h()->setStopSync(0);
            Mage::throwException(static::SUBSCRIBER_TYPE . ' sync has been stopped manually');
        }
    }

    /**
     * Write update, remove result logs
     */
    protected function _logStats()
    {
        if (isset($this->_stats['subscriber_count']) && $this->_stats['subscriber_count'] > 0) {

            $this->l()->log($this->l()->__('%s successfully subscribed %s/%s',
                static::SUBSCRIBER_TYPE, $this->_stats['subscriber_success_count'], $this->_stats['subscriber_count']
            ));

            if (!empty($this->_stats['subscriber_errors'])) {
                $this->l()->log($this->l()->__('%s subscribe errors %s/%s',
                    static::SUBSCRIBER_TYPE, var_export($this->_stats['subscriber_errors'], true)
                ));
            }
        }

        if (isset($this->_stats['unsubscriber_count']) && $this->_stats['unsubscriber_count'] > 0) {

            $this->l()->log($this->l()->__('%s successfully unsubscribed %s/%s',
                static::SUBSCRIBER_TYPE, $this->_stats['unsubscriber_success_count'], $this->_stats['unsubscriber_count']
            ));
            $this->l()->log($this->l()->__('%s unsubscribed with errors %s/%s',
                static::SUBSCRIBER_TYPE, $this->_stats['unsubscriber_error_count'], $this->_stats['unsubscriber_count']
            ));

            if (!empty($this->_stats['unsubscriber_errors'])) {
                $this->l()->log($this->l()->__('%s unsubscribe errors %s/%s',
                    static::SUBSCRIBER_TYPE, var_export($this->_stats['unsubscriber_errors'], true)
                ));
            }
        }
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Data
     */
    protected function h()
    {
        return Mage::helper('mailigen_synchronizer');
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Log
     */
    protected function l()
    {
        return Mage::helper('mailigen_synchronizer/log');
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Customer
     */
    protected function customerHelper()
    {
        return Mage::helper('mailigen_synchronizer/customer');
    }
}