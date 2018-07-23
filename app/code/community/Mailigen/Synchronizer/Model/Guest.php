<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Guest extends Mage_Newsletter_Model_Subscriber
{
    /**
     * @param $subscriberIds
     * @return int
     */
    public function setSynced($subscriberIds)
    {
        $subscriberIds = is_numeric($subscriberIds) ? array($subscriberIds) : $subscriberIds;
        return $this->_updateSyncedStatus($subscriberIds, true);
    }

    /**
     * @param $subscriberIds
     * @return int
     */
    public function setNotSynced($subscriberIds)
    {
        $subscriberIds = is_numeric($subscriberIds) ? array($subscriberIds) : $subscriberIds;
        return $this->_updateSyncedStatus($subscriberIds, false);
    }

    /**
     * @param array $subscriberIds
     * @param bool  $synced
     * @return int
     * @throws Mage_Core_Exception
     */
    protected function _updateSyncedStatus(array $subscriberIds, $synced = true)
    {
        $tableName = $this->getResource()->getTable('subscriber');
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $updated = $write->update(
            $tableName,
            array('mailigen_synced' => $synced ? 1 : 0),
            array(
                'customer_id = ?'      => '0',
                'subscriber_id IN (?)' => $subscriberIds,
            )
        );

        if ($updated < count($subscriberIds)) {
            Mage::throwException("Updated $updated subscribers of " . count($subscriberIds));
        }

        return $updated;
    }

    /**
     * Set not synced all subscribed guests
     *
     * @return int
     */
    public function setAllNotSynced()
    {
        $tableName = $this->getResource()->getTable('subscriber');
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $updated = $write->update(
            $tableName,
            array('mailigen_synced' => 0),
            array(
                'customer_id = ?'       => '0',
                'subscriber_status = ?' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
            )
        );

        return $updated;
    }
}