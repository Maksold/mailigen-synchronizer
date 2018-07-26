<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Resource_Default_Customer_Collection extends Mage_Customer_Model_Resource_Customer_Collection
{
    /**
     * @param array $customerIds
     * @return Mage_Customer_Model_Resource_Customer_Collection
     * @throws Mage_Core_Exception
     */
    public function getFullCustomerDataByIds($customerIds)
    {
        $this->addAttributeToSelect(
            array(
                'email',
                'firstname',
                'lastname',
                'prefix',
                'middlename',
                'suffix',
                'store_id',
                'group_id',
                'created_at',
                'dob',
                'gender',
                'is_active',
            )
        )->addAttributeToFilter('entity_id', array('in' => $customerIds));

        /**
         * Join Customer default billing address info
         */
        $this->joinAttribute('billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_region_id', 'customer_address/region_id', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');

        /**
         * Join Customer last login at
         */
        $logCustomerTableName = $this->getResource()->getTable('log/customer');
        $this->getSelect()->columns(array('last_login_at' => new Zend_Db_Expr("(SELECT login_at FROM $logCustomerTableName WHERE customer_id = e.entity_id ORDER BY log_id DESC LIMIT 1)")));

        /**
         * Join Customer order info
         */
        $this->joinTable(
            'mailigen_synchronizer/customer', 'id = entity_id', array(
                'lastorderdate'            => 'lastorderdate',
                'valueoflastorder'         => 'valueoflastorder',
                'totalvalueoforders'       => 'totalvalueoforders',
                'totalnumberoforders'      => 'totalnumberoforders',
                'numberofitemsincart'      => 'numberofitemsincart',
                'valueofcurrentcart'       => 'valueofcurrentcart',
                'lastitemincartaddingdate' => 'lastitemincartaddingdate',
            )
        );

        /**
         * Join Newsletter subscriber
         */
        $this->joinNewsletterSubscriber();

        return $this;
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function joinNewsletterSubscriber()
    {
        $this->joinTable(
            'newsletter/subscriber', 'customer_id = entity_id', array(
            'is_subscribed' => 'subscriber_status',
        ), null, 'left'
        );

        return $this;
    }

    /**
     * @param bool $isSubscribed
     * @return Mage_Customer_Model_Resource_Customer_Collection
     * @throws Mage_Core_Exception
     */
    public function addSubscriberStatusFilter(bool $isSubscribed = false)
    {
        if ($isSubscribed) {
            $newsletterTableName = $this->getTable('newsletter/subscriber');
            $this->getSelect()->where($newsletterTableName . '.subscriber_status = ?', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
        }

        return $this;
    }
}