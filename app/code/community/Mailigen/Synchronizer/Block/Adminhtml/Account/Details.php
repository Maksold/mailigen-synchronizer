<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_Adminhtml_Account_Details extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    const MAILIGEN_ACCOUNT_DETAILS_CACHE_ID = 'mailigen_synchronizer_account_details_data';

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $accountDetails = $this->_getAccountDetails();
        if ($accountDetails === null) {
            return '-';
        }

        /** @var $mailigenLists Mailigen_Synchronizer_Model_List */
        $mailigenLists = Mage::getSingleton('mailigen_synchronizer/list');
        $totalSubscribers = $mailigenLists->getTotalMembers();
        $maxSubscribers = $accountDetails->plan_high ?? 0;

        $subscribersPercentText = '';
        if ($totalSubscribers > 0 && $maxSubscribers > 0) {
            $subscribersPercent = round($totalSubscribers / $maxSubscribers * 100);
            $subscribersPercentText = '(' . $subscribersPercent . '%)';

            if ($subscribersPercent > 90) {
                $subscribersPercentText = '<b style="color: red;">' . $subscribersPercentText . '<b>';
            }
        }


        $html = '
            <style type="text/css">
                #mailigen_account_details_list { padding: 5px; color: #444; background-color: #fdfdfd; border: 1px solid #ccc; }
            </style>
            <ul class="checkboxes" id="mailigen_account_details_list">
                <li>Username: ' . ($accountDetails->username ?? '') . '</li>
                <li>Total Account Subscribers: ' . $totalSubscribers . '/' . $maxSubscribers . ' ' . $subscribersPercentText . '</li>
            </ul>';

        return $html;
    }

    /**
     * @return mixed|null
     */
    protected function _getAccountDetails()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $storeId = $helper->getScopeStoreId();
        $apiKey = $helper->getApiKey($storeId);

        $cacheId = self::MAILIGEN_ACCOUNT_DETAILS_CACHE_ID . '_' . $apiKey;
        $accountDetails = Mage::app()->loadCache($cacheId);

        if ($accountDetails === false) {
            $api = $helper->getMailigenApi($storeId);
            $accountDetails = json_encode($api->getAccountDetails());

            if ($api->errorCode) {
                return null;
            }

            Mage::app()->saveCache($accountDetails, $cacheId, array(Mage_Core_Model_Config::CACHE_TAG), 43200);
        }

        return json_decode($accountDetails);
    }
}