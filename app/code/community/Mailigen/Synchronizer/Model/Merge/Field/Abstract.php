<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
abstract class Mailigen_Synchronizer_Model_Merge_Field_Abstract
{
    /**
     * @var null|int
     */
    protected $_storeId = null;

    /**
     * @return array
     */
    abstract protected function _getMergeFields();

    /**
     * @return null|string
     * @throws Mage_Core_Exception
     */
    abstract public function getListId();

    /**
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId)
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * @return null|int
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * @throws Mage_Core_Exception
     */
    public function createMergeFields()
    {
        $api = $this->h()->getMailigenApi($this->getStoreId());
        $listId = $this->getListId();

        $createdFields = $this->_getCreatedMergeFields();
        $newFields = $this->_getMergeFields();

        foreach ($newFields as $tag => $options) {
            if (isset($createdFields[$tag])) {
                /**
                 * Merge Field already created
                 * Force update 'CUSTOMERGROUP' field, to make actual 'predefined_values'
                 */
                if ($tag == 'CUSTOMERGROUP') {
                    $api->listMergeVarUpdate($listId, $tag, $options);
                    if ($api->errorCode) {
                        Mage::throwException("Unable to update merge var. $api->errorCode: $api->errorMessage");
                    }
                }
            } else {
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
     * @throws Mage_Core_Exception
     */
    protected function _getCreatedMergeFields()
    {
        $api = $this->h()->getMailigenApi($this->getStoreId());
        $listId = $this->getListId();

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

    /**
     * @return Mailigen_Synchronizer_Helper_Data
     */
    protected function h()
    {
        return Mage::helper('mailigen_synchronizer');
    }

    /**
     * @param $values
     * @return string
     */
    protected function _getFormattedPredefinedValues($values)
    {
        if (is_array($values)) {
            return implode("||", $values);
        }

        return '';
    }
}