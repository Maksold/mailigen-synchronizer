<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Mailigen_Merge_Field
{
    /**
     * @var null|int
     */
    protected $_storeId = null;

    /**
     * @return null|string
     * @throws Mage_Core_Exception
     */
    public function getListId()
    {
        $listId = $this->h()->getContactList($this->getStoreId());
        if (empty($listId)) {
            Mage::throwException('Contact list isn\'t selected');
        }

        return $listId;
    }

    /**
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getMergeFields()
    {
        $mergeFields = array();
        $mapFields = $this->h()->getMapFields($this->getStoreId());

        foreach ($mapFields as $_mapField) {
            $mergeFields[$_mapField['mailigen']] = $this->_getMergeFieldData($_mapField['magento']);
        }

        return $mergeFields;
    }

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
                 * @todo Replace 'CUSTOMERGROUP'
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
     * @return Mailigen_Synchronizer_Helper_Customer
     */
    protected function customerHelper()
    {
        return Mage::helper('mailigen_synchronizer/customer');
    }

    /**
     * @param $values
     * @return string
     */
    protected function _getFormattedPredefinedValues($values)
    {
        if (is_array($values)) {
            return implode('||', $values);
        }

        return '';
    }

    /**
     * @param $id
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getMergeFieldData($id)
    {
        // Default values
        $fieldType = 'text';
        $predefinedValues = null;
        $required = false;


        if (is_numeric($id)) {
            /*
             * Customer attribute map field
             */
            $attributes = $this->customerHelper()->getAttributes();

            if (isset($attributes[$id])) {
                // Get title
                $title = $attributes[$id]['frontend_label'];

                // Get field_type, predefined_values, required
                switch ($attributes[$id]['attribute_code']) {
                    case 'group_id':
                        $fieldType = 'dropdown';
                        $predefinedValues = $this->_getFormattedPredefinedValues($this->customerHelper()->getCustomerGroups());
                        $required = true;
                        break;
                    case 'gender':
                        $fieldType = 'dropdown';
                        $predefinedValues = $this->_getFormattedPredefinedValues($this->customerHelper()->getGenders());
                        break;
                    case 'dob':
                        $fieldType = 'date';
                        break;
                }

            } else {
                $title = 'UNDEFINED CUSTOMER ATTRIBUTE - ' . $id;
            }
        } else {
            /*
             * Additional map field
             */
            $additionalMapField = $this->h()->getAdditionalMapFieldByCode($id, $this->getStoreId());

            if ($additionalMapField) {
                // Get title
                $title = $additionalMapField['label'];

                // Get field_type
                if (isset($additionalMapField['field_type'])) {
                    $fieldType = $additionalMapField['field_type'];
                }

                // Get required
                if (isset($additionalMapField['required']) && $additionalMapField['required']) {
                    $required = true;
                }

                // Get predefined_values
                switch ($id) {
                    case 'billing_country':
                        $predefinedValues = $this->_getFormattedPredefinedValues($this->customerHelper()->getCountries());
                        break;
                    case 'status_of_user':
                        $predefinedValues = $this->_getFormattedPredefinedValues($this->customerHelper()->customerStatus);
                        break;
                    case 'is_subscribed':
                        $predefinedValues = $this->_getFormattedPredefinedValues($this->customerHelper()->customerIsSubscribed);
                        break;
                }

            } else {
                $title = 'UNDEFINED ADDITIONAL ATTRIBUTE - ' . $id;
            }
        }

        return array(
            'title'             => $title,
            'field_type'        => $fieldType,
            'predefined_values' => $predefinedValues,
            'req'               => $required,
            'public'            => false,
        );
    }
}