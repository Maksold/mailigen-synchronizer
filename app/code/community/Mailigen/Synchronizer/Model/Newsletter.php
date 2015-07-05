<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Newsletter extends Mage_Newsletter_Model_Subscriber
{
    /**
     * @param     $subscriberIds
     * @return int
     */
    public function updateSyncedNewsletter($subscriberIds)
    {
        $tableName = $this->getResource()->getTable('subscriber');
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $updated = $write->update(
            $tableName,
            array('mailigen_synced' => 1),
            array('subscriber_id IN (?)' => $subscriberIds)
        );

        if ($updated < count($subscriberIds)) {
            Mage::throwException("Updated $updated subscribers of " . count($subscriberIds));
        }

        return $updated;
    }

    /**
     * @param      $subscriberId
     * @param bool $synced
     * @return int
     */
    public function updateIsSynced($subscriberId, $synced = true)
    {
        $tableName = $this->getResource()->getTable('subscriber');
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $updated = $write->update($tableName,
            array('mailigen_synced' => $synced ? 1 : 0),
            array('subscriber_id = ?' => $subscriberId)
        );

        return $updated;
    }

    /**
     * @return int
     */
    public function setNewsletterNotSynced()
    {
        $tableName = $this->getResource()->getTable('subscriber');
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $updated = $write->update($tableName,
            array('mailigen_synced' => 0),
            array('subscriber_status = ?' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED) // @todo Remove if unsubscribers will be synced too
        );

        return $updated;
    }
}