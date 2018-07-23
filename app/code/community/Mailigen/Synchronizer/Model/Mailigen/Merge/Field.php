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
     */
    protected function _getMergeFields()
    {
        /** @var $customerHelper Mailigen_Synchronizer_Helper_Customer */
        $customerHelper = Mage::helper('mailigen_synchronizer/customer');

        return array(
            /*
             * Basic fields
             */
            'WEBSITEID'                => array(
                'title'      => 'Website id',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'STOREID'                  => array(
                'title'      => 'Store id',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'STORELANGUAGE'            => array(
                'title'      => 'Store language',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            /*
             * Newsletter fields
             */
            'NEWSLETTERTYPE'           => array(
                'title'      => 'Newsletter type',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            /*
             * Customer fields
             */
            'PREFIX'                   => array(
                'title'      => 'Prefix',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'MIDDLENAME'               => array(
                'title'      => 'Middle name',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'SUFFIX'                   => array(
                'title'      => 'Suffix',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'CUSTOMERGROUP'            => array(
                'title'             => 'Customer group',
                'field_type'        => 'dropdown',
                'req'               => true,
                'predefined_values' => $this->_getFormattedPredefinedValues($customerHelper->getCustomerGroups()),
                'public'            => false,
            ),
            'PHONE'                    => array(
                'title'      => 'Phone',
                'field_type' => 'sms',
                'req'        => false,
                'public'     => false,
            ),
            'REGISTRATIONDATE'         => array(
                'title'      => 'Registration date',
                'field_type' => 'date',
                'req'        => true,
                'public'     => false,
            ),
            'COUNTRY'                  => array(
                'title'             => 'Country',
                'field_type'        => 'dropdown',
                'req'               => false,
                'predefined_values' => $this->_getFormattedPredefinedValues($customerHelper->getCountries()),
                'public'            => false,
            ),
            'CITY'                     => array(
                'title'      => 'City',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'REGION'                   => array(
                'title'      => 'State/Province',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'DATEOFBIRTH'              => array(
                'title'      => 'Date of birth',
                'field_type' => 'date',
                'req'        => false,
                'public'     => false,
            ),
            'GENDER'                   => array(
                'title'             => 'Gender',
                'field_type'        => 'dropdown',
                'req'               => false,
                'predefined_values' => $this->_getFormattedPredefinedValues($customerHelper->getGenders()),
                'public'            => false,
            ),
            'LASTLOGIN'                => array(
                'title'      => 'Last login',
                'field_type' => 'date',
                'req'        => false,
                'public'     => false,
            ),
            'CLIENTID'                 => array(
                'title'      => 'Client id',
                'field_type' => 'number',
                'req'        => true,
                'public'     => false,
            ),
            'STATUSOFUSER'             => array(
                'title'             => 'Status of user',
                'field_type'        => 'dropdown',
                'req'               => true,
                'predefined_values' => $this->_getFormattedPredefinedValues($customerHelper->customerStatus),
                'public'            => false,
            ),
            'ISSUBSCRIBED'             => array(
                'title'             => 'Is subscribed',
                'field_type'        => 'dropdown',
                'req'               => true,
                'predefined_values' => $this->_getFormattedPredefinedValues($customerHelper->customerIsSubscribed),
                'public'            => false,
            ),
            /*
             * Customer order info fields
             */
            'LASTORDERDATE'            => array(
                'title'      => 'Last order date',
                'field_type' => 'date',
                'req'        => false,
                'public'     => false,
            ),
            'VALUEOFLASTORDER'         => array(
                'title'      => 'Value of last order',
                'field_type' => 'number',
                'req'        => false,
                'public'     => false,
            ),
            'TOTALVALUEOFORDERS'       => array(
                'title'      => 'Total value of orders',
                'field_type' => 'number',
                'req'        => false,
                'public'     => false,
            ),
            'TOTALNUMBEROFORDERS'      => array(
                'title'      => 'Total number of orders',
                'field_type' => 'number',
                'req'        => false,
                'public'     => false,
            ),
            'NUMBEROFITEMSINCART'      => array(
                'title'      => 'Number of items in cart',
                'field_type' => 'number',
                'req'        => false,
                'public'     => false,
            ),
            'VALUEOFCURRENTCART'       => array(
                'title'      => 'Value of current cart',
                'field_type' => 'number',
                'req'        => false,
                'public'     => false,
            ),
            'LASTITEMINCARTADDINGDATE' => array(
                'title'      => 'Last item in cart adding date',
                'field_type' => 'date',
                'req'        => false,
                'public'     => false,
            ),
        );
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
            return implode('||', $values);
        }

        return '';
    }
}