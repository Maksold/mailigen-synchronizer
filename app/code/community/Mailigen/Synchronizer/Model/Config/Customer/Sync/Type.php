<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Config_Customer_Sync_Type extends Mage_Core_Model_Config_Data
{
    const SYNC_ALL_CUSTOMERS = 1;
    const SYNC_SUBSCRIBED_CUSTOMERS = 2;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('label' => 'Sync all customers', 'value' => self::SYNC_ALL_CUSTOMERS),
            array('label' => 'Sync only subscribed customers', 'value' => self::SYNC_SUBSCRIBED_CUSTOMERS),
        );
    }
}