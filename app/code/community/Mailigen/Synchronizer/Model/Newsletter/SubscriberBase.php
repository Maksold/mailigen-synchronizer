<?php

if (Mage::helper('core')->isModuleEnabled('Ebizmarts_MailChimp')
    && class_exists('Ebizmarts_MailChimp_Model_Subscriber')
) {
    class_alias('Ebizmarts_MailChimp_Model_Subscriber', 'Mailigen_Synchronizer_Model_Newsletter_SubscriberBase');
} else {
    class Mailigen_Synchronizer_Model_Newsletter_SubscriberBase extends Mage_Newsletter_Model_Subscriber
    {
    }
}