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
     * @param int|null $storeId
     * @return int
     * @throws Mage_Core_Exception
     */
    public function updateCustomersOrderInfo($storeId = null)
    {
        $customerIds = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToFilter('store_id', $storeId)
            ->getAllIds();
        $customerFlatIds = $this->getCollection()->getAllIds(false, false, $storeId);
        $newCustomerFlatIds = array_diff($customerIds, $customerFlatIds);

        if (count($newCustomerFlatIds) > 0) {
            $customers = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $newCustomerFlatIds))
                ->addAttributeToSelect(array('store_id', 'email'));

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
     * @throws Mage_Core_Exception
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
        if ($orders->getSize() > 0) {
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
            'id'                       => $customer->getId(),
            'email'                    => $customer->getEmail(),
            'website_id'               => $customer->getWebsiteId(),
            'store_id'                 => $customer->getStoreId(),
            'lastorderdate'            => $orders && $lastOrder ? $helper->getFormattedDate($lastOrder->getCreatedAt()) : '',
            'valueoflastorder'         => $orders && $lastOrder ? (float)$lastOrder->getGrandTotal() : '',
            'totalvalueoforders'       => (float)$totalGrandTotal,
            'totalnumberoforders'      => (int)$orders->getSize(),
            'numberofitemsincart'      => $quote ? (int)$quote->getItemsQty() : '',
            'valueofcurrentcart'       => $quote ? (float)$quote->getGrandTotal() : '',
            'lastitemincartaddingdate' => $quote ? $helper->getFormattedDate($quote->getUpdatedAt()) : '',
            'is_removed'               => 0,
            'is_synced'                => 0,
            'synced_at'                => null,
        );
    }

    public function _saveBatchedCustomersOrderInfo()
    {
        $tableName = $this->getResource()->getMainTable();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $insertFields = array_keys($this->_newCustomersOrderInfoData[0]);
        $idFieldKey = array_search('id', $insertFields);
        if ($idFieldKey) {
            unset($insertFields[$idFieldKey]); // Remove 'id' field
        }

        $write->insertOnDuplicate($tableName, $this->_newCustomersOrderInfoData, $insertFields);

        $this->_newCustomersOrderInfoData = array();
    }
    
    /**
     * @param array $customerIds
     * @return int
     * @throws Mage_Core_Exception
     */
    public function setSynced(array $customerIds)
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

    /**
     * @param      $customerId
     * @param bool $isRemoved
     * @return int
     */
    public function setNotSynced($customerId, $isRemoved = false)
    {
        $tableName = $this->getResource()->getMainTable();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $bind = array();
        $bind['is_synced'] = 0;
        if (is_int($isRemoved)) {
            $bind['is_removed'] = $isRemoved;
        }

        $updated = $write->update($tableName, $bind, array('id = ?' => $customerId));

        return $updated;
    }

    /**
     * @param bool $isRemoved
     * @return int
     */
    public function setAllNotSynced($isRemoved = false)
    {
        $tableName = $this->getResource()->getMainTable();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $bind = array();
        $bind['is_synced'] = 0;
        if (is_int($isRemoved)) {
            $bind['is_removed'] = $isRemoved;
        }

        $updated = $write->update($tableName, $bind);

        return $updated;
    }
}