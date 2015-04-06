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
        Mage::getModel('mailigen_synchronizer/customer_merge_field')->createMergeFields();

        // @todo Sync customers
    }
}