<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Resource_Subscriber_Collection extends Mage_Newsletter_Model_Resource_Subscriber_Collection
{
    /**
     * @param null $status
     * @param int  $synced
     * @param null $storeId
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getGuests($status = null, $synced = 0, $storeId = null)
    {
        $collection = $this->showCustomerInfo(true)
            ->addSubscriberTypeField()
            ->showStoreInfo()
            ->addFieldToFilter('type', Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_GUEST_TYPE)
            ->addFieldToFilter('mailigen_synced', $synced);

        if (null !== $status) {
            $collection->addFieldToFilter('subscriber_status', $status);
        }

        if (null !== $storeId) {
            $collection->addFieldToFilter('store_id', $storeId);
        }

        return $collection;
    }
}