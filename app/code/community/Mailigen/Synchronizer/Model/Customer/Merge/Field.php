<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Customer_Merge_Field extends Mage_Core_Model_Abstract
{
    /**
     * @return array
     */
    protected function _getMergeFieldsConfig()
    {
        return array(
            /**
             * Customer fields
             */
            'PREFIX' => array(
                'title' => 'Prefix',
                'field_type' => 'text',
                'req' => false
            ),
            'MIDDLENAME' => array(
                'title' => 'Middle name',
                'field_type' => 'text',
                'req' => false
            ),
            'SUFFIX' => array(
                'title' => 'Suffix',
                'field_type' => 'text',
                'req' => false
            ),
            'STOREID' => array(
                'title' => 'Store id',
                'field_type' => 'text',
                'req' => true
            ),
            'STORELANGUAGE' => array(
                'title' => 'Store language',
                'field_type' => 'text',
                'req' => true
            ),
            'CUSTOMERGROUP' => array(
                'title' => 'Customer group',
                'field_type' => 'grouping',
                'req' => true
                // @todo Add values
            ),
            'PHONE' => array(
                'title' => 'Phone',
                'field_type' => 'sms',
                'req' => false
            ),
            'REGISTRATIONDATE' => array(
                'title' => 'Registration date',
                'field_type' => 'date',
                'req' => true
            ),
            'COUNTRY' => array(
                'title' => 'Country',
                'field_type' => 'dropdown',
                'req' => true,
                'predefined_type' => 'countries'
                // @todo Add Predefined values
            ),
            'CITY' => array(
                'title' => 'City',
                'field_type' => 'text',
                'req' => false
            ),
            'DATEOFBIRTH' => array(
                'title' => 'Date of birth',
                'field_type' => 'date',
                'req' => false
            ),
            'GENDER' => array(
                'title' => 'Gender',
                'field_type' => 'dropdown',
                'req' => false
                // @todo Add Predefined values
            ),
            'LASTLOGIN' => array(
                'title' => 'Last login',
                'field_type' => 'date',
                'req' => true
            ),
            'CLIENTID' => array(
                'title' => 'Client id',
                'field_type' => 'number',
                'req' => true
            ),
            'STATUSOFUSER' => array(
                'title' => 'Status of user',
                'field_type' => 'dropdown',
                'req' => true
                // @todo Add values
            ),
            /**
             * Customer orders info
             */
            'LASTORDERDATE' => array(
                'title' => 'Last order date',
                'field_type' => 'date',
                'req' => false
            ),
            'VALUEOFLASTORDER' => array(
                'title' => 'Value of last order',
                'field_type' => 'number',
                'req' => false
            ),
            'TOTALVALUEOFORDERS' => array(
                'title' => 'Total value of orders',
                'field_type' => 'number',
                'req' => false
            ),
            'TOTALNUMBEROFORDERS' => array(
                'title' => 'Total number of orders',
                'field_type' => 'number',
                'req' => false
            ),
            'NUMBEROFITEMSINCART' => array(
                'title' => 'Number of items in cart',
                'field_type' => 'number',
                'req' => false
            ),
            'VALUEOFCURRENTCART' => array(
                'title' => 'Value of current cart',
                'field_type' => 'number',
                'req' => false
            ),
            'LASTITEMINCARTADDINGDATE' => array(
                'title' => 'Last item in cart adding date',
                'field_type' => 'date',
                'req' => false
            ),
            /**
             * @todo Add Discount coupon fields
             */
        );
    }

    public function createMergeFields()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $listId = $helper->getCustomersContactList();

        if (empty($listId)) {
            Mage::throwException("Contact list isn't selected");
        }

        $createdFields = $this->_getCreatedMergeFields();
        $newFields = $this->_getMergeFieldsConfig();

        foreach ($newFields as $tag => $options) {
            if (isset($createdFields[$tag])) {
                /**
                 * Merge Field already created
                 * @todo Update field if necessary
                 */
//                $api->listMergeVarDel($listId, $tag);
//                if ($api->errorCode) {
//                    Mage::throwException("Unable to delete merge var. $api->errorCode: $api->errorMessage");
//                }
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
     */
    protected function _getCreatedMergeFields()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');
        $api = $helper->getMailigenApi();
        $listId = $helper->getCustomersContactList();

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