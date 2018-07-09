<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Adminhtml_Observer
{
    /**
     * After Mailigen module config change:
     * 1. Create new Newsletter or Customer list in Mailigen
     * 2. Check and notify, if the same contact lists selected for Newsletter and Customers
     *
     * @param Varien_Event_Observer $observer
     */
    public function configChange(Varien_Event_Observer $observer)
    {
        /** @var $list Mailigen_Synchronizer_Model_List */
        $list = Mage::getModel('mailigen_synchronizer/list');
        /** @var $config Mage_Core_Model_Config */
        $config = new Mage_Core_Model_Config();
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
        $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');
        /** @var $configData Mage_Adminhtml_Model_Config_Data */
        $configData = Mage::getSingleton('adminhtml/config_data');
        $scope = $configData->getScope();
        $scopeId = $configData->getScopeId();
        $removeCache = false;

        /**
         * Create new newsletter list
         */
        $newsletterNewListName = $configData->getData($helper::XML_FULL_PATH_NEWSLETTER_NEW_LIST_TITLE);
        if (is_string($newsletterNewListName) && strlen($newsletterNewListName) > 0) {
            if ($mailigenSchedule->getLastRunningJob() === false) {
                $newListValue = $list->createNewList($newsletterNewListName);
                if ($newListValue) {
                    $config->saveConfig($helper::XML_PATH_NEWSLETTER_CONTACT_LIST, $newListValue, $scope, $scopeId);
                    $removeCache = true;

                    /**
                     * Set newsletter not synced on contact list change
                     */
                    /** @var $newsletter Mailigen_Synchronizer_Model_Newsletter */
                    $newsletter = Mage::getModel('mailigen_synchronizer/newsletter');
                    $newsletter->setNewsletterNotSynced();
                }
            }

            $config->deleteConfig($helper::XML_PATH_NEWSLETTER_NEW_LIST_TITLE, $scope, $scopeId);
        }

        /**
         * Create new customers list
         */
        $customersNewListName = $configData->getData($helper::XML_FULL_PATH_CUSTOMERS_NEW_LIST_TITLE);
        if (is_string($customersNewListName) && strlen($customersNewListName) > 0) {
            if ($mailigenSchedule->getLastRunningJob() === false) {
                $newListValue = $list->createNewList($customersNewListName);
                if ($newListValue) {
                    $config->saveConfig($helper::XML_PATH_CUSTOMERS_CONTACT_LIST, $newListValue, $scope, $scopeId);
                    $removeCache = true;

                    /**
                     * Set customers not synced on contact list change
                     */
                    /** @var $customer Mailigen_Synchronizer_Model_Customer */
                    $customer = Mage::getModel('mailigen_synchronizer/customer');
                    $customer->setCustomersNotSynced();
                }
            }

            $config->deleteConfig($helper::XML_PATH_CUSTOMERS_NEW_LIST_TITLE, $scope, $scopeId);
        }

        /**
         * Check and notify, if the same contact lists selected for Newsletter and Customers
         * @todo Check contact lists per each scope
         */
        $newsletterListId = $configData->getData($helper::XML_FULL_PATH_NEWSLETTER_CONTACT_LIST);
        $customerListId = $configData->getData($helper::XML_FULL_PATH_CUSTOMERS_CONTACT_LIST);
        if ($newsletterListId === $customerListId && strlen($newsletterListId) > 0) {
            Mage::getSingleton('adminhtml/session')->addWarning('Please select different contact lists for newsletter and customers');
            $config->deleteConfig($helper::XML_PATH_CUSTOMERS_CONTACT_LIST, $scope, $scopeId);
            $removeCache = true;
        }

        if ($removeCache) {
            $config->removeCache();
        }
    }

    /**
     * Add "Bulk synchronize with Mailigen" button to "Manage Customers" page in BE
     *
     * @param Varien_Event_Observer $observer
     */
    public function addCustomerBulkSyncButton(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();

        if ($block instanceof Mage_Adminhtml_Block_Customer && Mage::helper('mailigen_synchronizer')->isEnabled()) {
            $url = Mage::helper('adminhtml')->getUrl('*/mailigen/syncCustomers');
            $block->addButton(
                'synchronize', array(
                    'label'   => Mage::helper('adminhtml')->__('Bulk synchronize with Mailigen'),
                    'onclick' => "setLocation('{$url}')",
                    'class'   => 'task',
                )
            );
        }
    }
}