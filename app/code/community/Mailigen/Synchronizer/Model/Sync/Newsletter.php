<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Sync_Newsletter extends Mailigen_Synchronizer_Model_Sync_Abstract
{
    const SUBSCRIBER_TYPE = 'Guest';

    /**
     * @return Mailigen_Synchronizer_Model_Resource_Subscriber_Collection
     */
    protected function _getSubscribersCollection()
    {
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection');
        return $subscribers->getGuests(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 0, $this->_storeId);
    }

    /**
     * @return Mailigen_Synchronizer_Model_Resource_Subscriber_Collection
     */
    protected function _getUnsubscribersCollection()
    {
        /** @var $subscribers Mailigen_Synchronizer_Model_Resource_Subscriber_Collection */
        $subscribers = Mage::getResourceModel('mailigen_synchronizer/subscriber_collection');
        return $subscribers->getGuests(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 0, $this->_storeId);
    }

    /**
     * Set subscriber status to Synced
     *
     * @param array $batchData
     */
    protected function _afterSuccessBatchSubscribe(array $batchData)
    {
        /** @var $newsletter Mailigen_Synchronizer_Model_Newsletter */
        $newsletter = Mage::getModel('mailigen_synchronizer/newsletter');
        $newsletter->updateSyncedNewsletter(array_keys($batchData));
    }

    /**
     * Set unsubscriber status to Synced
     *
     * @param array $batchData
     */
    protected function _afterSuccessBatchUnsubscribe(array $batchData)
    {
        /** @var $newsletter Mailigen_Synchronizer_Model_Newsletter */
        $newsletter = Mage::getModel('mailigen_synchronizer/newsletter');
        $newsletter->updateSyncedNewsletter(array_keys($batchData));
    }
}