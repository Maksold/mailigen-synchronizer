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
     * @var null
     */
    protected $_listId;

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