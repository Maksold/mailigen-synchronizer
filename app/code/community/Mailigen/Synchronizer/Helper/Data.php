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
    const XML_PATH_CUSTOMERS_CONTACT_LIST = 'mailigen_synchronizer/customers/contact_list';
    const XML_PATH_CUSTOMERS_NEW_LIST_TITLE = 'mailigen_synchronizer/customers/new_list_title';
    const XML_PATH_CUSTOMERS_AUTOSYNC = 'mailigen_synchronizer/customers/autosync';

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
     * @param string $message
     * @todo Modify this function
     */
    public function log($message)
    {
//        if (!Mage::getStoreConfigFlag(self::XML_PATH_LOGGING_ENABLED)) {
//            return;
//        }
        Mage::log($message, null, 'mailigen_synchronizer.log');
    }
}