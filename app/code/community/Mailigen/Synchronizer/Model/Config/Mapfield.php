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
                    $unserializedValue = unserialize($serializedValue);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            $this->setValue($unserializedValue);
        }
    }
}
