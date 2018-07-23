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
     * Create new Mailigen contact list, if exists contact list title
     *
     * @param Varien_Event_Observer $observer
     */
    public function configChange(Varien_Event_Observer $observer)
    {
        /** @var $list Mailigen_Synchronizer_Model_List */
        $list = Mage::getModel('mailigen_synchronizer/list');
        /** @var $config Mage_Core_Model_Config */
        $config = new Mage_Core_Model_Config();
        /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
        $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');
        /** @var $configData Mage_Adminhtml_Model_Config_Data */
        $configData = Mage::getSingleton('adminhtml/config_data');
        $scope = $configData->getScope();
        $scopeId = $configData->getScopeId();
        $listTitle = $configData->getData(Mailigen_Synchronizer_Helper_Data::XML_FULL_PATH_CONTACT_LIST_TITLE);

        if (is_string($listTitle) && strlen($listTitle) > 0) {

            if ($mailigenSchedule->getLastRunningJob() === false) {

                $listId = $list->createNewList($listTitle);

                if ($listId) {
                    $config->saveConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CONTACT_LIST, $listId, $scope, $scopeId);
                    $config->removeCache();

                    /**
                     * Set guests not synced on contact list change
                     */
                    Mage::getModel('mailigen_synchronizer/newsletter')->setNewsletterNotSynced();

                    /**
                     * Set customers not synced on contact list change
                     */
                    Mage::getModel('mailigen_synchronizer/customer')->setCustomersNotSynced();
                }
            }

            $config->deleteConfig(Mailigen_Synchronizer_Helper_Data::XML_PATH_CONTACT_LIST_TITLE, $scope, $scopeId);
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