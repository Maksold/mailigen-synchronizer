<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Customer extends Mage_Core_Model_Abstract
{
    /**
     * @var array
     */
    protected $_newCustomersOrderInfoData = array();

    protected function _construct()
    {
        $this->_init('mailigen_synchronizer/customer');
    }

    /**
     * @return int
     */
    public function updateCustomersOrderInfo(){
        $customerIds = Mage::getModel('customer/customer')->getCollection()->getAllIds();
        $customerFlatIds = $this->getCollection()->getAllIds();
        $newCustomerFlatIds = array_diff($customerIds, $customerFlatIds);

        if (count($newCustomerFlatIds) > 0) {
            $customers = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToSelect('store_id')
                ->addAttributeToFilter('entity_id', array('in' => $newCustomerFlatIds));

            Mage::getSingleton('mailigen_synchronizer/resource_iterator_batched')->walk(
                $customers,
                array($this, '_prepareCustomerOrderInfoData'),
                array($this, '_saveBatchedCustomersOrderInfo')
            );
        }

        return count($newCustomerFlatIds);
    }

    /**
     * @param $customer
     */
    public function _prepareCustomerOrderInfoData($customer)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Customer */
        $helper = Mage::helper('mailigen_synchronizer/customer');
        /** @var $orders Mage_Sales_Model_Resource_Order_Collection */
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('status', Mage_Sales_Model_Order::STATE_COMPLETE)
            ->addAttributeToSort('created_at', 'desc')
            ->addAttributeToSelect('*');
        $lastOrder = $orders->getFirstItem();

        /**
         * Sum all orders grand total
         */
        $totalGrandTotal = 0;
        if ($orders->count() > 0) {
            foreach ($orders as $_order) {
                $totalGrandTotal += $_order->getGrandTotal();
            }
        }

        /**
         * Get customer cart info
         */
        $website = $helper->getWebsite($customer->getStoreId());
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getModel('sales/quote')->setWebsite($website);
        $quote->loadByCustomer($customer);

        $this->_newCustomersOrderInfoData[] = array(
            'id' => $customer->getId(),
            'lastorderdate' => $orders && $lastOrder ? $helper->getFormattedDate($lastOrder->getCreatedAt()) : '',
            'valueoflastorder' => $orders && $lastOrder ? (float)$lastOrder->getGrandTotal() : '',
            'totalvalueoforders' => (float)$totalGrandTotal,
            'totalnumberoforders' => (int)$orders->count(),
            'numberofitemsincart' => $quote ? (int)$quote->getItemsQty() : '',
            'valueofcurrentcart' => $quote ? (float)$quote->getGrandTotal() : '',
            'lastitemincartaddingdate' => $quote ? $helper->getFormattedDate($quote->getUpdatedAt()) : '',
            'is_removed' => 0,
            'is_synced' => 0,
            'synced_at' => null
        );
    }

    public function _saveBatchedCustomersOrderInfo()
    {
        $tableName = $this->getResource()->getMainTable();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $inserted = $write->insertMultiple($tableName, $this->_newCustomersOrderInfoData);
        if ($inserted < count($this->_newCustomersOrderInfoData)) {
            Mage::throwException("Saved $inserted customers of " . count($this->_newCustomersOrderInfoData));
        }
        $this->_newCustomersOrderInfoData = array();
    }

    /**
     * @param $customerIds
     * @return Mage_Customer_Model_Resource_Customer_Collection
     */
    public function getCustomerCollection($customerIds)
    {
        /** @var $customers Mage_Customer_Model_Resource_Customer_Collection */
        $customers = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect(array(
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
                'is_active'
            ))
            ->addAttributeToFilter('entity_id', array('in' => $customerIds));

        /**
         * Join Customer default billing address info
         */
        $customers->joinAttribute('billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');

        /**
         * Join Customer last login at
         */
        $logCustomerTableName = $this->getResource()->getTable('log/customer');
        $customers->getSelect()->columns(array('last_login_at' => new Zend_Db_Expr("(SELECT login_at FROM $logCustomerTableName WHERE customer_id = e.entity_id ORDER BY log_id DESC LIMIT 1)")));

        /**
         * Join Customer order info
         */
        $customers->joinTable('mailigen_synchronizer/customer', "id = entity_id", array(
            'lastorderdate',
            'valueoflastorder',
            'totalvalueoforders',
            'totalnumberoforders',
            'numberofitemsincart',
            'valueofcurrentcart',
            'lastitemincartaddingdate'
        ));

        return $customers;
    }

    /**
     * @param     $customerIds
     * @return int
     */
    public function updateSyncedCustomers($customerIds)
    {
        $tableName = $this->getResource()->getMainTable();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $updated = $write->update(
            $tableName,
            array('is_synced' => 1, 'synced_at' => Varien_Date::now()),
            array('id IN (?)' => $customerIds)
        );

        if ($updated < count($customerIds)) {
            Mage::throwException("Updated $updated customers of " . count($customerIds));
        }

        return $updated;
    }
}