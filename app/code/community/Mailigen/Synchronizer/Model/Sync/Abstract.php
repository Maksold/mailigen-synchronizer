<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Sync_Abstract
{
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
            $this->l()->log("Mailigen contact list isn't selected");
            return;
        }

        try {

            $this->l()->log('Sync started for Store Ids: ' . implode(', ', array_keys($mailigenLists)));

            foreach ($mailigenLists as $_storeId => $_listId) {
                $this->_storeId = (int)$_storeId;
                $this->_listId = $_listId;
                $this->l()->log('Sync started for Store Id: ' . $this->_storeId);

                $environment = $emulation->startEnvironmentEmulation($this->_storeId);
                $this->_resetStats();


                /**
                 * Create or update Merge fields
                 */
                Mage::getModel('mailigen_synchronizer/merge_field_customer')
                    ->setStoreId($this->_storeId)
                    ->createMergeFields();
                $this->l()->log('Merge fields created and updated');


                $this->doSync();


                $this->_logStats();

                $emulation->stopEnvironmentEmulation($environment);

                $this->l()->log('Sync finished for Store Id: ' . $this->_storeId);
                $this->_storeId = null;
                $this->_listId = null;
            }

            $this->l()->log('Sync finished for Store Ids: ' . implode(', ', array_keys($mailigenLists)));

        } catch (Exception $e) {
            $this->l()->logException($e);
        }
    }

    /**
     * Stop sync, if force sync stop is enabled
     */
    protected function _checkSyncStop()
    {
        if ($this->h()->getStopSync()) {
            $this->h()->setStopSync(0);
            Mage::throwException('Sync has been stopped manually');
        }
    }

    /**
     * Write update, remove result logs
     */
    protected function _logStats()
    {
        if (isset($this->_stats['subscriber_count']) && $this->_stats['subscriber_count'] > 0) {
            $this->l()->log("Successfully subscribed {$this->_stats['subscriber_success_count']}/{$this->_stats['subscriber_count']}");
            if (!empty($this->_stats['subscriber_errors'])) {
                $this->l()->log("Subscribe errors: " . var_export($this->_stats['subscriber_errors'], true));
            }
        }

        if (isset($this->_stats['unsubscriber_count']) && $this->_stats['unsubscriber_count'] > 0) {
            $this->l()->log("Successfully unsubscribed {$this->_stats['unsubscriber_success_count']}/{$this->_stats['unsubscriber_count']}");
            $this->l()->log("Unsubscribed with error {$this->_stats['unsubscriber_error_count']}/{$this->_stats['unsubscriber_count']}");
            if (!empty($this->_stats['unsubscriber_errors'])) {
                $this->l()->log("Unsubscribe errors: " . var_export($this->_stats['unsubscriber_errors'], true));
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