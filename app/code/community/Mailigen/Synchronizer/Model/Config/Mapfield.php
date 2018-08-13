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
    }
}
