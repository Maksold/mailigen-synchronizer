<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Config_Mapfield extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{
    /**
     * @return Mage_Core_Model_Abstract|void
     */
    protected function _afterLoad()
    {
        if (!is_array($this->getValue())) {
            if (is_object($this->getValue())) {
                $serializedValue = $this->getValue()->asArray();
            } else {
                $serializedValue = $this->getValue();
            }

            $unserializedValue = false;
            if (!empty($serializedValue)) {
                try {
                    $unserializedValue = json_decode($serializedValue, true);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $this->setValue($unserializedValue);
        }
    }


    /**
     * Unset array element with '__empty' key
     *
     * @return Mage_Core_Model_Abstract|void
     * @throws Mage_Core_Exception
     */
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            unset($value['__empty']);
        }
        $this->setValue($value);

        if (is_array($this->getValue())) {
            $this->setValue(json_encode($this->getValue()));
        }


        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $oldValue = json_encode($helper->getMapFields());

        if ($oldValue !== $this->getValue()) {

            /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
            $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');

            if ($mailigenSchedule->getLastRunningJob()) {
                /**
                 * Deny config modification, until synchronization will not be finished
                 */
                $this->_dataSaveAllowed = false;
                Mage::getSingleton('adminhtml/session')->addNotice($helper->__('You can\'t change customer map fields until synchronization will not be finished. Please try again after ~10 seconds.'));

                $helper->setStopSync(1);

            } else {

                /**
                 * Set guests not synced after contact list change
                 */
                Mage::getModel('mailigen_synchronizer/guest')->setAllNotSynced();

                /**
                 * Set customers not synced after customer sync type change
                 */
                Mage::getModel('mailigen_synchronizer/customer')->setAllNotSynced();
            }
        }
    }
}
