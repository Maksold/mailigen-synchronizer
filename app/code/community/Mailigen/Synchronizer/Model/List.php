<?php

class Mailigen_Synchronizer_Model_List extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('mailigen_synchronizer/list');
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $api = Mage::helper('mailigen_synchronizer')->getMailigenApi();
        $lists = $api->lists();

        if (!$api->errorCode && $lists) {
            $array[] = array('label' => '--Create a new list--', 'value' => '');
            foreach ($lists as $list) {
                $array[] = array('label' => $list['name'], 'value' => $list['id']);
            }
            return $array;
        }
    }
}
