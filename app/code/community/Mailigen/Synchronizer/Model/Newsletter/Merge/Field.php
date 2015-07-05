<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Newsletter_Merge_Field extends Mage_Core_Model_Abstract
{
    /**
     * Subscriber fields
     * @return array
     */
    protected function _getMergeFieldsConfig()
    {
        return array(
            'WEBSITEID' => array(
                'title' => 'Website id',
                'field_type' => 'text',
                'req' => false
            ),
            'TYPE' => array(
                'title' => 'Type',
                'field_type' => 'text',
                'req' => false
            ),
            'STOREID' => array(
                'title' => 'Store id',
                'field_type' => 'text',
                'req' => false
            ),
            'STORELANGUAGE' => array(
                'title' => 'Store language',
                'field_type' => 'text',
                'req' => false
            )
        );
    }

    public function createMergeFields()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $listId = $helper->getNewsletterContactList();
        if (empty($listId)) {
            Mage::throwException("Newsletter contact list isn't selected");
        }

        $createdFields = $this->_getCreatedMergeFields();
        $newFields = $this->_getMergeFieldsConfig();

        foreach ($newFields as $tag => $options) {
            if (!isset($createdFields[$tag])) {
                /**
                 * Create new merge field
                 */
                $name = $options['title'];
                $api->listMergeVarAdd($listId, $tag, $name, $options);
                if ($api->errorCode) {
                    Mage::throwException("Unable to add merge var. $api->errorCode: $api->errorMessage");
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function _getCreatedMergeFields()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $listId = $helper->getNewsletterContactList();

        $createdMergeFields = array();
        $tmpCreatedMergeFields = $api->listMergeVars($listId);
        if ($api->errorCode) {
            Mage::throwException("Unable to load merge vars. $api->errorCode: $api->errorMessage");
        }

        foreach ($tmpCreatedMergeFields as $mergeField) {
            $createdMergeFields[$mergeField['tag']] = $mergeField;
        }

        return $createdMergeFields;
    }
}