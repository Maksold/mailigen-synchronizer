<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Helper_Customer extends Mage_Core_Helper_Abstract
{
    /**
     * @var array
     */
    protected $_storeLang = array();

    /**
     * @var null|array
     */
    protected $_customerGroup = null;

    /**
     * @var null
     */
    protected $_website = null;

    /**
     * @var null
     */
    protected $_customerGender = null;

    /**
     * @var null
     */
    protected $_countries = null;

    /**
     * @var null
     */
    protected $_regions = null;

    /**
     * @var array
     */
    public $customerStatus = array(0 => 'Inactive', 1 => 'Active');

    /**
     * @var array
     */
    public $customerIsSubscribed = array(
        0 => 'Unsubscribed',
        1 => 'Subscribed',
        2 => 'Not Activated',
        3 => 'Unsubscribed',
        4 => 'Unconfirmed'
    );

    /**
     * @param $date
     * @return bool|string
     */
    public function getFormattedDate($date)
    {
        if (is_numeric($date)) {
            $date = date('d/m/Y', $date);
        } elseif (is_string($date) && !empty($date)) {
            $date = date('d/m/Y', strtotime($date));
        } else {
            $date = '';
        }
        return $date;
    }

    /**
     * @return array
     */
    public function getGenders()
    {
        if (is_null($this->_customerGender)) {
            $genders = Mage::getResourceSingleton('customer/customer')->getAttribute('gender')->getSource()->getAllOptions(false);
            foreach ($genders as $gender) {
                $this->_customerGender[$gender['value']] = $gender['label'];
            }
        }
        return $this->_customerGender;
    }
    /**
     * @param $gender
     * @return string
     */
    public function getFormattedGender($gender)
    {
        $genders = $this->getGenders();
        return (!is_null($gender) && isset($genders[$gender])) ? $genders[$gender] : '';
    }

    /**
     * @return array|null
     */
    public function getCustomerGroups()
    {
        if (is_null($this->_customerGroup)) {
            $this->_customerGroup = array();
            /** @var $groups Mage_Customer_Model_Resource_Group_Collection */
            $groups = Mage::getModel('customer/group')->getCollection();
            foreach ($groups as $group) {
                $this->_customerGroup[$group->getCustomerGroupId()] = $group->getCustomerGroupCode();
            }
        }
        return $this->_customerGroup;
    }

    /**
     * @param $groupId
     * @return string
     */
    public function getCustomerGroup($groupId)
    {
        $groups = $this->getCustomerGroups();
        return isset($groups[$groupId]) ? $groups[$groupId] : '';
    }

    /**
     * @param $status
     * @return string
     */
    public function getFormattedCustomerStatus($status)
    {
        return $status ? $this->customerStatus[1] : $this->customerStatus[0];
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getStoreLanguage($storeId)
    {
        if (!isset($this->_storeLang[$storeId])) {
            $this->_storeLang[$storeId] = substr(Mage::getStoreConfig('general/locale/code', $storeId), 0, 2);
        }
        return $this->_storeLang[$storeId];
    }

    /**
     * @param $storeId
     * @return mixed
     */
    public function getWebsite($storeId)
    {
        if (!isset($this->_website[$storeId])) {
            /** @var $store Mage_Core_Model_Store */
            $store = Mage::getModel('core/store')->load($storeId);
            $this->_website[$storeId] = $store->getWebsite();
        }
        return $this->_website[$storeId];
    }

    /**
     * @return array
     */
    public function getCountries()
    {
        if ($this->_countries === null) {
            $countries = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);
            foreach ($countries as $country) {
                $this->_countries[$country['value']] = $country['label'];
            }
        }
        return $this->_countries;
    }
    /**
     * @return array
     */
    public function getRegions()
    {
        if ($this->_regions === null) {
            $regionCollection = Mage::getModel('directory/region')->getCollection()->getData();
            foreach ($regionCollection as $region) {
                $this->_regions[$region['region_id']] = $region['name'];
            }
        }
        return $this->_regions;
    }

    /**
     * @param $country
     * @return string
     */
    public function getFormattedCountry($country)
    {
        $countries = $this->getCountries();
        return isset($countries[$country]) ? $countries[$country] : '';
    }

    /**
     * @param $regionId
     * @return string
     */
    public function getFormattedRegion($regionId)
    {
        $regions = $this->getRegions();
        return isset($regions[$regionId]) ? $regions[$regionId] : '';
    }

    /**
     * @param $isSubscribed
     * @return string
     */
    public function getFormattedIsSubscribed($isSubscribed)
    {
        if ($isSubscribed === null) {
            return $this->customerIsSubscribed[0];
        } elseif (isset($this->customerIsSubscribed[$isSubscribed])) {
            return $this->customerIsSubscribed[$isSubscribed];
        } else {
            return $this->customerIsSubscribed[0];
        }
    }

    /**
     * @param $type
     * @return mixed
     */
    public function getSubscriberType($type)
    {
        if ($type == 1) {
            return Mage::helper('newsletter')->__('Guest');
        } elseif ($type == 2) {
            return Mage::helper('newsletter')->__('Customer');
        } else {
            return Mage::helper('newsletter')->__('Unknown');
        }
    }
}