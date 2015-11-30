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
    const XML_PATH_NEWSLETTER_CONTACT_LIST = 'mailigen_synchronizer/newsletter/contact_list';
    const XML_PATH_NEWSLETTER_NEW_LIST_TITLE = 'mailigen_synchronizer/newsletter/new_list_title';
    const XML_PATH_NEWSLETTER_AUTOSYNC = 'mailigen_synchronizer/newsletter/autosync';
    const XML_PATH_NEWSLETTER_HANDLE_DEFAULT_EMAILS = 'mailigen_synchronizer/newsletter/handle_default_emails';
    const XML_PATH_NEWSLETTER_WEBHOOKS = 'mailigen_synchronizer/newsletter/webhooks';
    const XML_PATH_NEWSLETTER_WEBHOOKS_SECRET_KEY = 'mailigen_synchronizer/newsletter/webhooks_secret_key';
    const XML_PATH_CUSTOMERS_CONTACT_LIST = 'mailigen_synchronizer/customers/contact_list';
    const XML_PATH_CUSTOMERS_NEW_LIST_TITLE = 'mailigen_synchronizer/customers/new_list_title';
    const XML_PATH_CUSTOMERS_AUTOSYNC = 'mailigen_synchronizer/customers/autosync';
    const XML_PATH_SYNC_MANUAL = 'mailigen_synchronizer/sync/manual';
    const XML_PATH_SYNC_STOP = 'mailigen_synchronizer/sync/stop';

    protected $_mgapi = array();
    protected $_storeIds = null;

    /**
     * @param null $storeId
     * @return bool
     * @todo Check, where this function is used
     */
    public function isEnabled($storeId = null)
    {
        if (is_null($storeId)) {
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
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_API_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getNewsletterContactList($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_NEWSLETTER_CONTACT_LIST, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canAutoSyncNewsletter($storeId = null)
    {
        if (is_null($storeId)) {
            $storeIds = $this->getStoreIds();
            if (count($storeIds) > 0) {
                foreach ($storeIds as $_storeId) {
                    if (Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_AUTOSYNC, $_storeId)) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_AUTOSYNC, $storeId);
        }
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canNewsletterHandleDefaultEmails($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_HANDLE_DEFAULT_EMAILS, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function enabledWebhooks($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_WEBHOOKS, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getWebhooksSecretKey($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_NEWSLETTER_WEBHOOKS_SECRET_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return string
     */
    public function generateWebhooksSecretKey($storeId = null)
    {
        $config = new Mage_Core_Model_Config();

        $secretKey = bin2hex(openssl_random_pseudo_bytes(24));

        if (is_null($storeId) || $storeId == Mage_Core_Model_App::ADMIN_STORE_ID) {
            $config->saveConfig(self::XML_PATH_NEWSLETTER_WEBHOOKS_SECRET_KEY, $secretKey);
        }
        else {
            $store = Mage::getModel('core/store')->load($storeId);
            $scopeId = $store->getWebsiteId();
            $config->saveConfig(self::XML_PATH_NEWSLETTER_WEBHOOKS_SECRET_KEY, $secretKey, 'websites', $scopeId);
        }
        $config->cleanCache();

        return $secretKey;
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getCustomersContactList($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        return Mage::getStoreConfig(self::XML_PATH_CUSTOMERS_CONTACT_LIST, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canAutoSyncCustomers($storeId = null)
    {
        if (is_null($storeId)) {
            $storeIds = $this->getStoreIds();
            if (count($storeIds) > 0) {
                foreach ($storeIds as $_storeId) {
                    if (Mage::getStoreConfigFlag(self::XML_PATH_CUSTOMERS_AUTOSYNC, $_storeId)) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return Mage::getStoreConfigFlag(self::XML_PATH_CUSTOMERS_AUTOSYNC, $storeId);
        }
    }

    /**
     * @param null $storeId
     * @return MGAPI|mixed
     */
    public function getMailigenApi($storeId = null)
    {
        $storeId = is_null($storeId) ? $this->getDefaultStoreId() : $storeId;
        if (!isset($this->_mgapi[$storeId])) {
            require_once Mage::getBaseDir('lib') . '/mailigen/MGAPI.class.php';
            $this->_mgapi[$storeId] = new MGAPI($this->getApiKey($storeId), false, true);
        }

        return $this->_mgapi[$storeId];
    }

    /**
     * @param int $start
     */
    public function setManualSync($start = 1)
    {
        $config = new Mage_Core_Model_Config();
        $config->saveConfig(self::XML_PATH_SYNC_MANUAL, $start);
        $config->cleanCache();
    }

    /**
     * @return bool
     */
    public function getManualSync()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_SYNC_MANUAL);
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
        }
        else {
            $result = false;
        }

        return $result;
    }

    /**
     * Get Store ids with enable Mailigen Sync
     * @return array
     */
    public function getStoreIds()
    {
        if (is_null($this->_storeIds)) {
            $this->_storeIds = array();
            $websites = Mage::app()->getWebsites();
            foreach ($websites as $_website) {
                $storeId = $_website->getDefaultGroup()->getDefaultStore()->getId();
                if ($this->isEnabled($storeId)) {
                    array_push($this->_storeIds, $storeId);
                }
            }
        }

        return $this->_storeIds;
    }

    /**
     * @return array
     */
    public function getNewsletterContactLists()
    {
        $storesIds = $this->getStoreIds();
        $result = array();
        foreach ($storesIds as $_storeId) {
            $list = $this->getNewsletterContactList($_storeId);
            if (strlen($list) > 0) {
                $result[$_storeId] = $list;
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getCustomerContactLists()
    {
        $storesIds = $this->getStoreIds();
        $result = array();
        foreach ($storesIds as $_storeId) {
            $list = $this->getCustomersContactList($_storeId);
            if (strlen($list) > 0) {
                $result[$_storeId] = $list;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getDefaultStoreId()
    {
        return Mage::app()->getWebsite()->getDefaultGroup()->getDefaultStoreId();
    }

    /**
     * @return int
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
     * @param      $datetime
     * @param bool $full
     * @return string
     */
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    /**
     * @param $data
     * @param $signature
     * @param null $storeId
     * @return bool
     */
    public function verifySignature($data, $signature, $storeId = null)
    {
        $secretKey = $this->getWebhooksSecretKey($storeId);
        $hash = hash_hmac('sha1', $data, $secretKey);
        return $signature === 'sha1=' . $hash;
    }
}