<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Helper_Mapfield extends Mage_Core_Helper_Abstract
{
    const WEBSITE_ID      = 'website_id';
    const STORE_ID        = 'store_id';
    const STORE_LANGUAGE  = 'store_language';
    const NEWSLETTER_TYPE = 'newsletter_type';
    const IS_SUBSCRIBED   = 'is_subscribed';

    const CUSTOMER_ID            = 'client_id';
    const CUSTOMER_GROUP         = 'group_id';
    const CUSTOMER_CREATED_AT    = 'registration_date';
    const CUSTOMER_DATE_OF_BIRTH = 'dob';
    const CUSTOMER_GENDER        = 'gender';
    const CUSTOMER_LAST_LOG_IN   = 'last_login';
    const CUSTOMER_IS_ACTIVE     = 'status_of_user';
    const BILLING_COUNTRY        = 'billing_country';
    const BILLING_CITY           = 'billing_city';
    const BILLING_REGION         = 'billing_region';

    /**
     * @var array
     */
    protected $_basicFields = array(
        self::WEBSITE_ID,
        self::STORE_ID,
        self::STORE_LANGUAGE,
        self::NEWSLETTER_TYPE,
        self::IS_SUBSCRIBED,
    );

    /**
     * @param null $storeId
     * @param bool $customerFields
     * @param bool $additionalFields
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getMappedFields($storeId = null, $customerFields = true, $additionalFields = true)
    {
        $result = array();
        $customerAttributes = Mage::helper('mailigen_synchronizer/customer')->getAttributes();
        $mapFields = Mage::helper('mailigen_synchronizer')->getMapFields($storeId);

        foreach ($mapFields as $_mapField) {
            $attributeId = $_mapField['magento'];

            if ($customerFields && is_numeric($attributeId) && isset($customerAttributes[$attributeId])) {
                $attributeId = $customerAttributes[$attributeId]['attribute_code'];

                $result[$attributeId] = $_mapField['mailigen'];
            } elseif ($additionalFields) {

                $result[$attributeId] = $_mapField['mailigen'];
            }
        }

        return $result;
    }

    /**
     * @param null $storeId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getAllMappedFields($storeId = null)
    {
        return $this->_getMappedFields($storeId, true, true);
    }

    /**
     * @param null $storeId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getCustomerMappedFields($storeId = null)
    {
        return $this->_getMappedFields($storeId, true, false);
    }

    /**
     * @param null $storeId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getBasicMappedFields($storeId = null)
    {
        return array_filter($this->getAllMappedFields($storeId),
            function ($_attributeCode) {
                return in_array($_attributeCode, $this->_basicFields, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param null $storeId
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getNoneBasicMappedFields($storeId = null)
    {
        return array_filter($this->getAllMappedFields($storeId),
            function ($_attributeCode) {
                return !in_array($_attributeCode, $this->_basicFields, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param string                                                        $_attributeCode
     * @param Mage_Customer_Model_Customer|Mage_Newsletter_Model_Subscriber $_subscriber
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getMappedFieldValue($_attributeCode, $_subscriber)
    {
        switch ($_attributeCode) {
            case self::NEWSLETTER_TYPE:
                $value = $this->customerHelper()->getSubscriberType($_subscriber->getType());
                break;
            case self::STORE_LANGUAGE:
                $value = $this->customerHelper()->getStoreLanguage($_subscriber->getStoreId());
                break;
            case self::CUSTOMER_ID:
                $value = $_subscriber->getId();
                break;
            case self::CUSTOMER_GROUP:
                $value = $this->customerHelper()->getCustomerGroup($_subscriber->getGroupId());
                break;
            case self::CUSTOMER_CREATED_AT:
                $value = $this->customerHelper()->getFormattedDate($_subscriber->getCreatedAtTimestamp());
                break;
            case self::CUSTOMER_DATE_OF_BIRTH:
                $value = $this->customerHelper()->getFormattedDate($_subscriber->getDob());
                break;
            case self::CUSTOMER_GENDER:
                $value = $this->customerHelper()->getFormattedGender($_subscriber->getGender());
                break;
            case self::CUSTOMER_LAST_LOG_IN:
                $value = $this->customerHelper()->getFormattedDate($_subscriber->getLastLoginAt());
                break;
            case self::CUSTOMER_IS_ACTIVE:
                $value = $this->customerHelper()->getFormattedCustomerStatus($_subscriber->getIsActive());
                break;
            case self::IS_SUBSCRIBED:
                $value = $this->customerHelper()->getFormattedIsSubscribed($_subscriber->getData('subscriber_status'));
                break;
            case self::BILLING_COUNTRY:
                $value = $this->customerHelper()->getFormattedCountry($_subscriber->getBillingCountryId());
                break;
            case self::BILLING_REGION:
                $value = $this->customerHelper()->getFormattedRegion($_subscriber->getBillingRegionId());
                break;
            default:
                $value = $_subscriber->getData($_attributeCode);
                break;
        }

        if ($value === null) {
            $value = '';
        }

        return $value;
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Customer
     */
    protected function customerHelper()
    {
        return Mage::helper('mailigen_synchronizer/customer');
    }

    /**
     * @param array $mapFields
     * @return array
     */
    public function reformatMailigenMergeFields($mapFields)
    {
        $reformattedMapFields = array();

        if (count($mapFields)) {
            foreach ($mapFields as $mapFieldKey => $mapField) {
                // Replace whitespaces with "_"
                $mapField['mailigen'] = preg_replace('/[\s]/', '_', trim($mapField['mailigen']));
                // Remove all extra symbols, except letters, digits and "-", "_"
                $mapField['mailigen'] = preg_replace('/[^a-zA-Z0-9\-\_]/', '', $mapField['mailigen']);
                // Convert to UPPERCASE
                $mapField['mailigen'] = strtoupper($mapField['mailigen']);

                if ($mapField['mailigen'] !== '') {
                    $reformattedMapFields[$mapFieldKey] = $mapField;
                }
            }
        }

        return $reformattedMapFields;
    }
}