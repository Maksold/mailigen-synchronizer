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

    public function getSubscribers($status = null, $synced = 0)
    {
        $collection = $this->showCustomerInfo(true)
            ->addSubscriberTypeField()
            ->showStoreInfo()
            ->addFieldToFilter('mailigen_synced', $synced);

        if (!is_null($status)) {
            $collection->addFieldToFilter('subscriber_status', $status);
        }

        return $collection;
    }
}