<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_ENABLED = 'mailigen_synchronizer/general/enabled';
    const XML_PATH_API_KEY = 'mailigen_synchronizer/general/api_key';
    const XML_PATH_CONTACT_LIST = 'mailigen_synchronizer/general/contact_list';
    const XML_PATH_CONTACT_LIST_TITLE = 'mailigen_synchronizer/general/contact_list_title';
    const XML_PATH_CUSTOMER_SYNC_TYPE = 'mailigen_synchronizer/general/customer_sync_type';
    const XML_PATH_MAP_FIELDS = 'mailigen_synchronizer/general/map_fields';
    const XML_PATH_ADDITIONAL_MAP_FIELDS = 'mailigen_synchronizer/general/additional_map_fields';
    const XML_PATH_HANDLE_DEFAULT_EMAILS = 'mailigen_synchronizer/general/handle_default_emails';
    const XML_PATH_WEBHOOKS_ENABLED = 'mailigen_synchronizer/webhooks/enabled';
    const XML_PATH_WEBHOOKS_SECRET_KEY = 'mailigen_synchronizer/webhooks/secret_key';
    const XML_PATH_BATCH_SIZE = 'mailigen_synchronizer/advanced/batch_size';
    const XML_PATH_BATCH_LIMIT = 'mailigen_synchronizer/advanced/batch_limit';
    const XML_PATH_SYNC_STOP = 'mailigen_synchronizer/sync/stop';
    const XML_FULL_PATH_CONTACT_LIST_TITLE = 'groups/general/fields/contact_list_title/value';

    const DEFAULT_BATCH_SIZE = 100;
    const DEFAULT_BATCH_LIMIT = 10000;

    protected $_mgapi = array();
    protected $_storeIds;

    /**
     * @param null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        if (null === $storeId) {
            $storeIds = $this->getStoreIds();
            if (count($storeIds) > 0) {
                foreach ($storeIds as $_storeId) {
                    if (Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $_storeId)) {
                        return true;
                    }
                }
            }

            return false;
        } else {
            return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
        }
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getApiKey($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_API_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getContactList($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_CONTACT_LIST, $storeId);
    }

    /**
     * @param null $storeId
     * @return int
     */
    public function getCustomerSyncType($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return (int)Mage::getStoreConfig(self::XML_PATH_CUSTOMER_SYNC_TYPE, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isSyncAllCustomers($storeId = null)
    {
        return $this->getCustomerSyncType($storeId) === Mailigen_Synchronizer_Model_Config_Customer_Sync_Type::SYNC_ALL_CUSTOMERS;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function isSyncSubscribedCustomers($storeId = null)
    {
        return $this->getCustomerSyncType($storeId) === Mailigen_Synchronizer_Model_Config_Customer_Sync_Type::SYNC_SUBSCRIBED_CUSTOMERS;
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canHandleDefaultEmails($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfigFlag(self::XML_PATH_HANDLE_DEFAULT_EMAILS, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function enabledWebhooks($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfigFlag(self::XML_PATH_WEBHOOKS_ENABLED, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getWebhooksSecretKey($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_WEBHOOKS_SECRET_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return int
     */
    public function getBatchSize($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        $batchSize = (int)Mage::getStoreConfig(self::XML_PATH_BATCH_SIZE, $storeId);
        return $batchSize > 0 ? $batchSize : self::DEFAULT_BATCH_SIZE;
    }

    /**
     * @param null $storeId
     * @return int
     */
    public function getBatchLimit($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        $batchLimit = (int)Mage::getStoreConfig(self::XML_PATH_BATCH_LIMIT, $storeId);
        return $batchLimit > 0 ? $batchLimit : self::DEFAULT_BATCH_LIMIT;
    }

    /**
     * @param null $storeId
     * @return string
     */
    public function generateWebhooksSecretKey($storeId = null)
    {
        $config = new Mage_Core_Model_Config();

        $secretKey = bin2hex(openssl_random_pseudo_bytes(24));

        if (null === $storeId || $storeId == Mage_Core_Model_App::ADMIN_STORE_ID) {
            $config->saveConfig(self::XML_PATH_WEBHOOKS_SECRET_KEY, $secretKey);
        } else {
            $store = Mage::getModel('core/store')->load($storeId);
            $scopeId = $store->getWebsiteId();
            $config->saveConfig(self::XML_PATH_WEBHOOKS_SECRET_KEY, $secretKey, 'websites', $scopeId);
        }

        $config->cleanCache();

        return $secretKey;
    }

    /**
     * @param null $storeId
     * @return MGAPI|mixed
     */
    public function getMailigenApi($storeId = null)
    {
        $storeId = null === $storeId ? $this->getDefaultStoreId() : $storeId;
        if (!isset($this->_mgapi[$storeId])) {
            require_once Mage::getBaseDir('lib') . '/mailigen/MGAPI.class.php';
            $this->_mgapi[$storeId] = new MGAPI($this->getApiKey($storeId), false, true);
        }

        return $this->_mgapi[$storeId];
    }

    /**
     * @param int $stop
     */
    public function setStopSync($stop = 1)
    {
        $config = new Mage_Core_Model_Config();
        $config->saveConfig(self::XML_PATH_SYNC_STOP, $stop);
    }

    /**
     * Get stop sync value directly from DB
     *
     * @return bool
     */
    public function getStopSync()
    {
        /** @var $stopSyncConfigCollection Mage_Core_Model_Resource_Config_Data_Collection */
        $stopSyncConfigCollection = Mage::getModel('core/config_data')->getCollection()
            ->addFieldToFilter('path', self::XML_PATH_SYNC_STOP);

        if ($stopSyncConfigCollection->getSize()) {
            /** @var $stopSyncConfig Mage_Core_Model_Config_Data */
            $stopSyncConfig = $stopSyncConfigCollection->getFirstItem();
            $result = ($stopSyncConfig->getValue() == '1');
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * Get Store ids with enable Mailigen Sync
     *
     * @return array
     */
    public function getStoreIds()
    {
        if (null === $this->_storeIds) {
            $this->_storeIds = array();
            $websites = Mage::app()->getWebsites();
            foreach ($websites as $_website) {
                $storeIds = $_website->getStoreIds();
                foreach ($storeIds as $storeId) {
                    if ($this->isEnabled($storeId)) {
                        $this->_storeIds[] = $storeId;
                    }
                }
            }
        }

        return $this->_storeIds;
    }

    /**
     * @return array
     */
    public function getContactLists()
    {
        $storesIds = $this->getStoreIds();
        $result = array();
        foreach ($storesIds as $_storeId) {
            $list = $this->getContactList($_storeId);
            if (strlen($list) > 0) {
                $result[$_storeId] = $list;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getDefaultStoreId()
    {
        return Mage::app()->getWebsite()->getDefaultGroup()->getDefaultStoreId();
    }

    /**
     * @return int
     * @throws Mage_Core_Exception
     */
    public function getScopeStoreId()
    {
        if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore())) // store level
        {
            return Mage::getModel('core/store')->load($code)->getId();
        } elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) // website level
        {
            $website_id = Mage::getModel('core/website')->load($code)->getId();
            return Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
        } else // default level
        {
            return Mage_Core_Model_App::ADMIN_STORE_ID;
        }
    }

    /**
     * @param      $data
     * @param      $signature
     * @param null $storeId
     * @return bool
     */
    public function verifyWebhooksSignature($data, $signature, $storeId = null)
    {
        $secretKey = $this->getWebhooksSecretKey($storeId);
        $hash = hash_hmac('sha1', $data, $secretKey);
        return $signature === 'sha1=' . $hash;
    }

    /**
     * Create string for current scope with format scope-scopeId.
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getCurrentScope()
    {
        $scopeIdArray = $this->getConfigScopeId();
        $scopeArray = array();
        if (isset($scopeIdArray['websiteId'])) {
            $scopeArray['scope'] = 'websites';
            $scopeArray['scope_id'] = $scopeIdArray['websiteId'];
        } elseif (isset($scopeIdArray['storeId'])) {
            $scopeArray['scope'] = 'stores';
            $scopeArray['scope_id'] = $scopeIdArray['storeId'];
        } else {
            $scopeArray['scope'] = 'default';
            $scopeArray['scope_id'] = 0;
        }
        return $scopeArray;
    }

    /**
     * Get storeId and/or websiteId if scope selected on back end
     *
     * @param  null $storeId
     * @param  null $websiteId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getConfigScopeId($storeId = null, $websiteId = null)
    {
        $scopeArray = array();
        if ($code = Mage::getSingleton('adminhtml/config_data')->getStore()) {
            // store level
            $storeId = Mage::getModel('core/store')->load($code)->getId();
        } elseif ($code = Mage::getSingleton('adminhtml/config_data')->getWebsite()) {
            // website level
            $websiteId = Mage::getModel('core/website')->load($code)->getId();
            $storeId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
        }
        $scopeArray['websiteId'] = $websiteId;
        $scopeArray['storeId'] = $storeId;
        return $scopeArray;
    }

    /**
     * Get Config value for certain scope.
     *
     * @param       $path
     * @param       $scopeId
     * @param  null $scope
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getConfigValueForScope($path, $scopeId, $scope = null)
    {
        if ($scope === 'websites') {
            $configValue = Mage::app()->getWebsite($scopeId)->getConfig($path);
        } else {
            $configValue = Mage::getStoreConfig($path, $scopeId);
        }
        return $configValue;
    }


    /**
     * Get custom merge fields configured for the given scope.
     *
     * @param       $scopeId
     * @param  null $scope
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getAdditionalMergeFieldsSerialized($scopeId, $scope = null)
    {
        return $this->getConfigValueForScope(Mailigen_Synchronizer_Helper_Data::XML_PATH_ADDITIONAL_MAP_FIELDS, $scopeId, $scope);
    }

    /**
     * Get custom merge fields for given scope as an array.
     *
     * @param       $scopeId
     * @param  null $scope
     * @return array|mixed
     * @throws Mage_Core_Exception
     */
    public function getAdditionalMergeFields($scopeId, $scope = null)
    {
        $customMergeFields = unserialize($this->getAdditionalMergeFieldsSerialized($scopeId, $scope));
        if (!$customMergeFields) {
            $customMergeFields = array();
        }
        return $customMergeFields;
    }
}