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
    protected $_lists;

    public function _construct()
    {
        parent::_construct();
        $this->_init('mailigen_synchronizer/list');
    }

    /**
     * @param bool $forceLoad
     * @return array|null
     */
    public function getLists($forceLoad = false)
    {
        if (null === $this->_lists || $forceLoad) {
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
                $array[] = array(
                    'label' => $list['name'] . ' (' . $list['member_count'] . ' members)',
                    'value' => $list['id']
                );
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
        foreach ($lists as $list) {
            if ($list['label'] == $newListName) {
                Mage::getSingleton('adminhtml/session')->addWarning("A list with name '$newListName' already existed");
                return $list['value'];
            }
        }

        //Only if a list with a similar name is not doesn't exists we move further.
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $storeId = $helper->getScopeStoreId();

        $options = array(
            'permission_reminder'   => ' ',
            'notify_to'             => Mage::getStoreConfig('trans_email/ident_general/email'),
            'subscription_notify'   => true,
            'unsubscription_notify' => true,
            'has_email_type_option' => true
        );

        $api = $helper->getMailigenApi($storeId);
        $newListId = $api->listCreate($newListName, $options);

        if ($api->errorCode) {
            $logger->log("Unable to create list. $api->errorCode: $api->errorMessage");
            return false;
        }

        sleep(3); // Wait 3 seconds until Mailigen API updates data

        return $newListId;
    }

    /**
     * @param null $lists
     * @return Mailigen_Synchronizer_Model_List
     */
    public function setLists($lists)
    {
        $this->_lists = $lists;
        return $this;
    }

    /**
     * @param bool $forceListLoad
     * @return int
     */
    public function getTotalMembers($forceListLoad = false)
    {
        $totalMembers = 0;
        $lists = $this->getLists($forceListLoad);

        if (is_array($lists) && count($lists)) {
            foreach ($lists as $list) {
                if (isset($list['member_count'])) {
                    $totalMembers += (int)$list['member_count'];
                }
            }
        }

        return $totalMembers;
    }
}
