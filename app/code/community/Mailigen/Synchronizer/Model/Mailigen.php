<?php

class Mailigen_Synchronizer_Model_Mailigen extends Mage_Core_Model_Abstract
{
    public function syncNewsletter()
    {
        $api = Mage::helper('mailigen_synchronizer')->getMailigenApi();
        $listid = Mage::helper('mailigen_synchronizer')->getNewsletterContactList();
        if (!$listid) {
            return;
        }

        //First we pull all unsubscribers from Mailigen
        $unsubscribers = $api->listMembers($listid, "unsubscribed", 0, 500);

        foreach ($unsubscribers as $unsubscriber) {

            $email = $unsubscriber['email'];

            // create new subscriber without send an confirmation email
            Mage::getModel('newsletter/subscriber')->setImportMode(true)->subscribe($email);

            // get just generated subscriber
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            // change status to "unsubscribed" and save
            $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
            $subscriber->save();
        }

        //Second we pull all subscribers from Mailigen
        $subscribers = $api->listMembers($listid, "subscribed", 0, 500);

        foreach ($subscribers as $subscriber) {

            $email = $subscriber['email'];


            // create new subscriber without send an confirmation email
            Mage::getModel('newsletter/subscriber')->setImportMode(true)->subscribe($email);

            // get just generated subscriber
            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);

            // change status to "unsubscribed" and save
            $subscriber->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
            $subscriber->save();
        }

        //and finally we push our list to mailigen
        $collection = Mage::getResourceSingleton('newsletter/subscriber_collection');
        $collection->showCustomerInfo(true)->addSubscriberTypeField()->showStoreInfo();

        $batch = array();
        foreach ($collection as $subscriber) {

            $batch[] = array(
                'EMAIL' => $subscriber->getSubscriberEmail(),
                'FNAME' => $subscriber->getCustomerFirstname(),
                'LNAME' => $subscriber->getCustomerLastname()
            );
        }

        $double_optin = false;
        $update_existing = true;
        $retval = $api->listBatchSubscribe($listid, $batch, $double_optin, $update_existing);

        if ($api->errorCode) {
            Mage::getSingleton('adminhtml/session')->addError("Something went wrong");
            Mage::log("Mailigen API Error: " . "Code=" . $api->errorCode . " Msg=" . $api->errorMessage);
        } else {
            Mage::getSingleton('adminhtml/session')->addSuccess("Your contacts have been syncronized");
            Mage::log("Returned: " . $retval);
        }
    }

    public function syncCustomers()
    {
        /** @var $logger Mailigen_Synchronizer_Helper_Log */
        $logger = Mage::helper('mailigen_synchronizer/log');
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $logger->log('Customers synchronization started');
        $listId = $helper->getCustomersContactList();
        if (!$listId) {
            Mage::throwException("Customer contact list isn't selected");
        }

        /**
         * Create or update Merge fields
         */
        Mage::getModel('mailigen_synchronizer/customer_merge_field')->createMergeFields();
        $logger->log('Merge fields created and updated');

        /**
         * Sync Customers
         */
        $api = $helper->getMailigenApi();

        $customerCount = Mage::getModel('customer/customer')->getCollection()->count();
        $customerPerStep = 100;
        $maxI = ceil($customerCount/$customerPerStep);
        $successSyncCount = 0;
        $errorSyncCount = 0;
        $syncErrors = array();

        for ($i = 1; $i <= $maxI; $i++) {
            $batchCustomers = $this->_getCustomers($i, $customerPerStep);
            $retval = $api->listBatchSubscribe($listId, $batchCustomers, false, true);

            $logger->log("Processed " . ($i < $maxI ? $i * $customerPerStep : $customerCount) . "/$customerCount customers");

            if ($api->errorCode){
                Mage::throwException("Unable to batch subscribe. $api->errorCode: $api->errorMessage");
            } else {
                $successSyncCount += $retval['success_count'];
                $errorSyncCount += $retval['error_count'];
                if (count($retval['errors'])) {
                    $syncErrors = array_merge_recursive($syncErrors, $retval['errors']);
                }
            }
        }

        $logger->log('Customers synchronization finished');
        $logger->log("Synced successfully $successSyncCount/$customerCount customers");
        $logger->log("Synced with error $errorSyncCount/$customerCount customers");
        if (!empty($syncErrors)) {
            $logger->log("Sync errors: " . var_export($syncErrors, true));
        }
    }

    /**
     * @param int $page
     * @param int $limit
     * @return array
     */
    protected function _getCustomers($page = 1, $limit = 100)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Customer */
        $helper = Mage::helper('mailigen_synchronizer/customer');
        // @todo Walk collection
        $customers = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToSelect('*')
                ->setCurPage($page)
                ->setPageSize($limit);
        $customersArray = array();

        foreach ($customers as $customer)
        {
            $logCustomer = Mage::getModel('log/customer')->load($customer->getId());
            $customerAddress = $customer->getDefaultBillingAddress();

            $customersArray[$customer->getEntityId()] = array(
                /**
                 * Customer info
                 */
                'EMAIL' => $customer->getEmail(),
                'FNAME' => $customer->getFirstname(),
                'LNAME' => $customer->getLastname(),
                'PREFIX' => $customer->getPrefix(),
                'MIDDLENAME' => $customer->getMiddlename(),
                'SUFFIX' => $customer->getSuffix(),
                'STOREID' => $customer->getStoreId(),
                'STORELANGUAGE' => $helper->getStoreLanguage($customer->getStoreId()),
                'CUSTOMERGROUP' => $helper->getCustomerGroup($customer->getGroupId()),
                'PHONE' => ($customerAddress ? $customerAddress->getTelephone() : ''),
                'REGISTRATIONDATE' => $helper->getFormattedDate($customer->getCreatedAtTimestamp()),
                'COUNTRY' => $customerAddress ? $helper->getFormattedCountry($customerAddress->getCountryId()) : '',
                'CITY' => $customerAddress ? $customerAddress->getCity() : '',
                'DATEOFBIRTH' => $helper->getFormattedDate($customer->getDob()),
                'GENDER' => $helper->getFormattedGender($customer->getGender()),
                'LASTLOGIN' => $helper->getFormattedDate($logCustomer->getLoginAtTimestamp()),
                'CLIENTID' => $customer->getId(),
                'STATUSOFUSER' => $helper->getFormattedCustomerStatus($customer->getIsActive()),
            );

            /**
             * Add customer orders info
             */
            $customersArray[$customer->getEntityId()] = array_merge(
                $customersArray[$customer->getEntityId()], $this->_getCustomerOrderInfo($customer)
            );
        }

        return $customersArray;
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    public function _getCustomerOrderInfo(Mage_Customer_Model_Customer $customer)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Customer */
        $helper = Mage::helper('mailigen_synchronizer/customer');
        /** @var $orders Mage_Sales_Model_Resource_Order_Collection */
        $orders = Mage::getModel("sales/order")->getCollection()
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

        return array(
            'LASTORDERDATE' => $orders && $lastOrder ? $helper->getFormattedDate($lastOrder->getCreatedAt()) : '',
            'VALUEOFLASTORDER' => $orders && $lastOrder ? (float)$lastOrder->getGrandTotal() : '',
            'TOTALVALUEOFORDERS' => (float)$totalGrandTotal,
            'TOTALNUMBEROFORDERS' => (int)$orders->count(),
            'NUMBEROFITEMSINCART' => $quote ? (int)$quote->getItemsQty() : '',
            'VALUEOFCURRENTCART' => $quote ? (float)$quote->getGrandTotal() : '',
            'LASTITEMINCARTADDINGDATE' => $quote ? $helper->getFormattedDate($quote->getUpdatedAt()) : ''
        );
    }
}