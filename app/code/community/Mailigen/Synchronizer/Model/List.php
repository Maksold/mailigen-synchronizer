<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_List extends Mage_Core_Model_Abstract
{
    /**
     * @var null
     */
    protected $_lists = null;

    public function _construct()
    {
        parent::_construct();
        $this->_init('mailigen_synchronizer/list');
    }

    /**
     * @param bool $load
     * @return array|null
     */
    public function getLists($load = false)
    {
        if (is_null($this->_lists) || $load) {
            /** @var $helper Mailigen_Synchronizer_Helper_Data */
            $helper = Mage::helper('mailigen_synchronizer');
            $storeId = $helper->getScopeStoreId();
            $api = $helper->getMailigenApi($storeId);
            $this->_lists = $api->lists();
        }
        return $this->_lists;
    }

    /**
     * @param bool $load
     * @return array
     */
    public function toOptionArray($load = false)
    {
        $lists = $this->getLists($load);

        if (is_array($lists) && !empty($lists)) {
            $array[] = array('label' => '--Create a new list--', 'value' => '');
            foreach ($lists as $list) {
                $array[] = array('label' => $list['name'], 'value' => $list['id']);
            }
            return $array;
        }
    }

    /**
     * @param $newListName
     * @return bool|string
     */
    public function createNewList($newListName)
    {
        //Get the list with current lists
        $lists = $this->toOptionArray();

        //Check if a similar list name doesn't exists already.
        $continue = true;
        foreach ($lists as $list) {
            if ($list['label'] == $newListName) {
                $continue = false;
                Mage::getSingleton('adminhtml/session')->addError("A list with name '$newListName' already exists");
                break;
            }
        }

        //Only if a list with a similar name is not doesn't exists we move further.
        if ($continue) {

            /** @var $logger Mailigen_Synchronizer_Helper_Log */
            $logger = Mage::helper('mailigen_synchronizer/log');
            /** @var $helper Mailigen_Synchronizer_Helper_Data */
            $helper = Mage::helper('mailigen_synchronizer');
            $storeId = $helper->getScopeStoreId();

            $options = array(
                'permission_reminder' => ' ',
                'notify_to' => Mage::getStoreConfig('trans_email/ident_general/email'),
                'subscription_notify' => true,
                'unsubscription_notify' => true,
                'has_email_type_option' => true
            );

            $api = $helper->getMailigenApi($storeId);
            $retval = $api->listCreate($newListName, $options);

            if ($api->errorCode) {
                $logger->log("Unable to create list. $api->errorCode: $api->errorMessage");
            }

            //We grab the list one more time
            $lists = $this->toOptionArray(true);
            foreach ($lists as $list) {
                if ($list['label'] == $newListName) {
                    //We make the new submitted list default
                    return $list['value'];
                }
            }
        }

        return false;
    }
}
