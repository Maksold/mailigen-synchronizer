<?php

/**
 * Class Mailigen_Synchronizer_Model_Mailigen_Api
 */
class Mailigen_Synchronizer_Model_Mailigen_Api
{
    protected $_mgapi = array();
    protected $_storeId = 0;
    protected $_response = null;

    /**
     * @var bool Send confirmation email to subscriber
     */
    public $subscribeDoubleOptin = false;

    /**
     * @var bool Restore existing email. If not, then return error message.
     */
    public $subscribeUpdateExisting = true;

    /**
     * @var bool Remove or not remove unsubscriber from list
     */
    public $unsubscribeDeleteMember = false;

    /**
     * @var bool Send or not send goodbuy email to unsubscriber
     */
    public $unsubscribeSendGoodbuy = false;

    /**
     * @var bool Send or not send notification email (which is configured in list settings) to unsubscriber
     */
    public $unsubscribeSendNotify = false;

    /**
     * @return MGAPI|mixed
     */
    public function api()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        if (!$this->getStoreId()) {
            $this->setStoreId($helper->getDefaultStoreId());
        }

        if (!isset($this->_mgapi[$this->getStoreId()])) {
            require_once Mage::getBaseDir('lib') . '/mailigen/MGAPI.class.php';
            $apiKey = $helper->getApiKey($this->getStoreId());
            $this->_mgapi[$this->getStoreId()] = new MGAPI($apiKey, false, true);
        }

        return $this->_mgapi[$this->getStoreId()];
    }

    /**
     * @param $listId
     * @param $batchData
     * @return null|struct
     */
    public function listBatchSubscribe($listId, $batchData)
    {
        $this->_response = $this->api()->listBatchSubscribe(
            $listId,
            $batchData,
            $this->subscribeDoubleOptin,
            $this->subscribeUpdateExisting
        );

        return $this->_response;
    }

    /**
     * @param $listId
     * @param $batchData
     * @return null|struct
     */
    public function listBatchUnsubscribe($listId, $batchData)
    {
        $this->_response = $this->api()->listBatchUnsubscribe(
            $listId,
            $batchData,
            $this->unsubscribeDeleteMember,
            $this->unsubscribeSendGoodbuy,
            $this->unsubscribeSendNotify
        );

        return $this->_response;
    }

    /**
     * @param int $storeId
     * @return Mailigen_Synchronizer_Model_Mailigen_Api
     */
    public function setStoreId(int $storeId): Mailigen_Synchronizer_Model_Mailigen_Api
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->_storeId;
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return $this->api()->errorCode !== '';
    }

    /**
     * @return null|string
     */
    public function getJsonErrorInfo()
    {
        $errorInfo = null;
        if ($this->hasError()) {
            $errorInfo = json_encode(array(
                'ERROR'    => $this->api()->errorCode . ': ' . $this->api()->errorMessage,
                'RESPONSE' => $this->_response,
            ));
        }

        return $errorInfo;
    }

    /**
     * @return null
     */
    public function getErrors()
    {
        return (isset($this->_response['errors']) && count($this->_response['errors']) > 0)
            ? $this->_response['errors']
            : array();
    }

    /**
     * @return null
     */
    public function getErrorCount()
    {
        return isset($this->_response['error_count']) ? $this->_response['error_count'] : null;
    }

    /**
     * @return null
     */
    public function getSuccessCount()
    {
        return isset($this->_response['success_count']) ? $this->_response['success_count'] : null;
    }
}