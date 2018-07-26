<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Sync_Customer extends Mailigen_Synchronizer_Model_Sync_Abstract
{
    const SUBSCRIBER_TYPE = 'Customer';

    /**
     * Update customers order info
     */
    protected function _beforeSubscribe()
    {
        parent::_beforeSubscribe();

        $updatedCustomers = Mage::getModel('mailigen_synchronizer/customer')->updateCustomersOrderInfo($this->_storeId);
        $this->l()->log('Updated ' . $updatedCustomers . ' customers in flat table');
    }

    /**
     * Delete unsubscribed customers from Mailige list, if configured to sync only subscribed customers
     */
    protected function _beforeUnsubscribe()
    {
        parent::_beforeUnsubscribe();

        $this->_getMailigenApi()->unsubscribeDeleteMember = true;
    }

    /**
     * @return Mage_Customer_Model_Resource_Customer_Collection
     * @throws Mage_Core_Exception
     */
    protected function _getSubscribersCollection()
    {
        $customerIds = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->getAllIds(0, 0, $this->_storeId);

        /** @var $customers Mailigen_Synchronizer_Model_Resource_Default_Customer_Collection */
        $customers = Mage::getResourceModel('mailigen_synchronizer/default_customer_collection');
        $customers->getFullCustomerDataByIds($customerIds);

        if ($this->h()->isSyncSubscribedCustomers($this->_storeId)) {
            /*
             * Subscribe only subscribed customers
             */
            $customers->addSubscribedFilter();
        }

        return $customers;
    }

    /**
     * @return Mailigen_Synchronizer_Model_Resource_Customer_Collection
     */
    protected function _getUnsubscribersCollection()
    {
        /** @var $removeCustomer Mailigen_Synchronizer_Model_Resource_Customer_Collection */
        $customers = Mage::getModel('mailigen_synchronizer/customer')->getCollection();
        $customers->addFieldToFilter('is_synced', 0)
            ->addStoreFilter($this->_storeId)
            ->addFieldToSelect(array('id', 'email'));

        if ($this->h()->isSyncAllCustomers($this->_storeId)) {
            /*
             * Unsubscribe removed customers
             */
            $customers->addIsRemovedFilter(true);
        } else {
            /*
            * Unsubscribe unsubscribed OR removed customers
            */
            $customers->addUnsubscribedOrIsRemovedFilter(true);
        }

        return $customers;
    }

    /**
     * @param Mage_Customer_Model_Customer|Mage_Newsletter_Model_Subscriber $subscriber
     */
    public function _prepareBatchSubscribeData($subscriber)
    {
        /*
         * Load Basic fields
         */
        parent::_prepareBatchSubscribeData($subscriber);

        /*
         * Add Customer specific fields
         */
        if ($subscriber instanceof Mage_Customer_Model_Customer) {
            $customerFields = array(
                'FNAME'                    => $subscriber->getFirstname(),
                'LNAME'                    => $subscriber->getLastname(),
                'PREFIX'                   => $subscriber->getPrefix(),
                'MIDDLENAME'               => $subscriber->getMiddlename(),
                'SUFFIX'                   => $subscriber->getSuffix(),
                'CUSTOMERGROUP'            => $this->customerHelper()->getCustomerGroup($subscriber->getGroupId()),
                'PHONE'                    => $subscriber->getBillingTelephone(),
                'REGISTRATIONDATE'         => $this->customerHelper()->getFormattedDate($subscriber->getCreatedAtTimestamp()),
                'COUNTRY'                  => $this->customerHelper()->getFormattedCountry($subscriber->getBillingCountryId()),
                'CITY'                     => $subscriber->getBillingCity(),
                'REGION'                   => $this->customerHelper()->getFormattedRegion($subscriber->getBillingRegionId()),
                'DATEOFBIRTH'              => $this->customerHelper()->getFormattedDate($subscriber->getDob()),
                'GENDER'                   => $this->customerHelper()->getFormattedGender($subscriber->getGender()),
                'LASTLOGIN'                => $this->customerHelper()->getFormattedDate($subscriber->getLastLoginAt()),
                'CLIENTID'                 => $subscriber->getId(),
                'STATUSOFUSER'             => $this->customerHelper()->getFormattedCustomerStatus($subscriber->getIsActive()),
                'ISSUBSCRIBED'             => $this->customerHelper()->getFormattedIsSubscribed($subscriber->getData('subscriber_status')),
                /**
                 * Customer orders info
                 */
                'LASTORDERDATE'            => $subscriber->getData('lastorderdate'),
                'VALUEOFLASTORDER'         => $subscriber->getData('valueoflastorder'),
                'TOTALVALUEOFORDERS'       => $subscriber->getData('totalvalueoforders'),
                'TOTALNUMBEROFORDERS'      => $subscriber->getData('totalnumberoforders'),
                'NUMBEROFITEMSINCART'      => $subscriber->getData('numberofitemsincart'),
                'VALUEOFCURRENTCART'       => $subscriber->getData('valueofcurrentcart'),
                'LASTITEMINCARTADDINGDATE' => $subscriber->getData('lastitemincartaddingdate'),
            );

            $this->_batchedData[$subscriber->getId()] += $customerFields;
        }
    }

    /**
     * Set customer (subscriber) status to Synced
     *
     * @param array $batchData
     */
    protected function _afterSuccessBatchSubscribe(array $batchData)
    {
        Mage::getModel('mailigen_synchronizer/customer')->setSynced(array_keys($batchData));
    }

    /**
     * Set customer (unsubscriber) status to Synced
     *
     * @param array $batchData
     */
    protected function _afterSuccessBatchUnsubscribe(array $batchData)
    {
        Mage::getModel('mailigen_synchronizer/customer')->setSynced(array_keys($batchData));
    }
}