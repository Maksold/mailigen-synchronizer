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
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $listId = $helper->getCustomersContactList();
        if (!$listId) {
            Mage::throwException("Customer contact list isn't selected");
        }

        /**
         * Create or update Merge fields
         */
        Mage::getModel('mailigen_synchronizer/customer_merge_field')->createMergeFields();

        /**
         * Sync Customers
         */
        $api = $helper->getMailigenApi();

        $customerCount = Mage::getModel('customer/customer')->getCollection()->count();
        $customerPerStep = 100;
        $maxI = ceil($customerCount/$customerPerStep);
        $maxI = 1; // @todo remove

        for ($i = 1; $i <= $maxI; $i++) {
            $batchCustomers = $this->_getCustomers($i, $customerPerStep);
            $retval = $api->listBatchSubscribe($listId, $batchCustomers, false, true);

            if ($api->errorCode){
                Mage::throwException("Unable to batch subscribe. $api->errorCode: $api->errorMessage");
            } else {
                $c = "success:".$retval['success_count']."\n";
                $c = "errors:".$retval['error_count']."\n";
                foreach($retval['errors'] as $val){
                    $c = "\t*".$val['email']. " failed\n";
                    $c = "\tcode:".$val['code']."\n";
                    $c = "\tmsg :".$val['message']."\n\n";
                }
            }
        }
    }

    /**
     * @param int $start
     * @param int $limit
     * @return array
     */
    protected function _getCustomers($start = 1, $limit = 100)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Customer */
        $helper = Mage::helper('mailigen_synchronizer/customer');
        // @todo Walk collection
        $customers = Mage::getModel('customer/customer')->getCollection()
                ->addAttributeToSelect('*')
                ->setCurPage($start)
                ->setPageSize($limit);
        $customersArray = array();

        foreach ($customers as $customer)
        {
            $logCustomer = Mage::getModel('log/customer')->load($customer->getId());
            $customerAddress = $customer->getDefaultBillingAddress();

            $customersArray[$customer->getEntityId()] = array(
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
                'COUNTRY' => $customerAddress ? $customerAddress->getCountryId() : '',
                'CITY' => $customerAddress ? $customerAddress->getCity() : '',
                'DATEOFBIRTH' => $helper->getFormattedDate($customer->getDob()),
                'GENDER' => $helper->getFormattedGender($customer->getGender()),
                'LASTLOGIN' => $helper->getFormattedDate($logCustomer->getLoginAtTimestamp()),
                'CLIENTID' => $customer->getId(),
                'STATUSOFUSER' => $helper->getFormattedCustomerStatus($customer->getIsActive()),
            );
        }

        return $customersArray;
    }
}