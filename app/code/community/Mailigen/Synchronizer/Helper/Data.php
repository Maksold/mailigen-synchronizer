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
    const XML_PATH_CUSTOMERS_CONTACT_LIST = 'mailigen_synchronizer/customers/contact_list';
    const XML_PATH_CUSTOMERS_NEW_LIST_TITLE = 'mailigen_synchronizer/customers/new_list_title';
    const XML_PATH_CUSTOMERS_AUTOSYNC = 'mailigen_synchronizer/customers/autosync';
    const XML_PATH_SYNC_MANUAL = 'mailigen_synchronizer/sync/manual';
    const XML_PATH_SYNC_STOP = 'mailigen_synchronizer/sync/stop';

    protected $_mgapi = null;

    /**
     * @param null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getApiKey($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_API_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getNewsletterContactList($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_NEWSLETTER_CONTACT_LIST, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canAutoSyncNewsletter($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_AUTOSYNC, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canNewsletterHandleDefaultEmails($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_HANDLE_DEFAULT_EMAILS, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function enabledWebhooks($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_NEWSLETTER_WEBHOOKS, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getCustomersContactList($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_CUSTOMERS_CONTACT_LIST, $storeId);
    }

    /**
     * @param null $storeId
     * @return bool
     */
    public function canAutoSyncCustomers($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_CUSTOMERS_AUTOSYNC, $storeId);
    }

    /**
     * @return MGAPI|null
     */
    public function getMailigenApi()
    {
        if (is_null($this->_mgapi)) {
            require_once Mage::getBaseDir('lib') . '/mailigen/MGAPI.class.php';
            $this->_mgapi = new MGAPI($this->getApiKey());
        }

        return $this->_mgapi;
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
}