<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_System_Config_Sync_Information
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        $lastSyncedText = $this->_getLastSyncedText();
        $syncedGuestsProgress = $this->_getSyncedGuestsProgress();
        $syncedCustomersProgress = $this->_getSyncedCustomersProgress();
        $syncStatusText = $this->_getSyncStatusText();

        $html = '<style type="text/css">
            .progress {
              position: relative;
              padding: 2px;
              background: rgba(0, 0, 0, 0.25);
              border-radius: 6px;
              -webkit-box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25), 0 1px rgba(255, 255, 255, 0.08);
              box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25), 0 1px rgba(255, 255, 255, 0.08);
            }
            .progress-bar {
              text-indent: 6px;
              position: relative;
              height: 16px;
              border-radius: 4px;
              background-color: #86e01e;
              -webkit-transition: 0.4s linear;
              -moz-transition: 0.4s linear;
              -ms-transition: 0.4s linear;
              -o-transition: 0.4s linear;
              transition: 0.4s linear;
              -webkit-transition-property: width, background-color;
              -moz-transition-property: width, background-color;
              -ms-transition-property: width, background-color;
              -o-transition-property: width, background-color;
              transition-property: width, background-color;
              -webkit-box-shadow: 0 0 1px 1px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.1);
              box-shadow: 0 0 1px 1px rgba(0, 0, 0, 0.25), inset 0 1px rgba(255, 255, 255, 0.1);

            }
            .progress-bar:before, .progress-bar:after {
              content: "";
              top: 0;
              right: 0;
              left: 0;
              position: absolute;
            }
            .progress-bar:before {
              bottom: 0;
              z-index: 2;
              border-radius: 4px 4px 0 0;
            }
            .progress-bar:after {
              bottom: 45%;
              z-index: 3;
              border-radius: 4px;
              background-color: transparent;
              background-image: -webkit-gradient(linear, left top, left bottom, color-stop(0%, rgba(255, 255, 255, 0.3)), color-stop(100%, rgba(255, 255, 255, 0.05)));
              background-image: -webkit-linear-gradient(top, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.05));
              background-image: -moz-linear-gradient(top, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.05));
              background-image: -ms-linear-gradient(top, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.05));
              background-image: -o-linear-gradient(top, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.05));
              background-image: linear-gradient(top, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.05));
            }
            .progress-text {
              position: absolute;
              top: 1px;
              left: 10px;
              font-size: 12px;
              color: #111;
              text-align: left;
            }
            </style>
            <table cellspacing="0" class="form-list">
                <tr>
                    <td class="label">' . $helper->__('Last synced') . '</td>
                    <td class="value">' . $lastSyncedText . '</td>
                    <td class="scope-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">' . $helper->__('Sync status') . '</td>
                    <td class="value">' . $syncStatusText . '</td>
                    <td class="scope-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">' . $helper->__('Synced guests') . '</td>
                    <td class="value">
                        <div class="progress">
                            <div class="progress-bar" style="width:' . $syncedGuestsProgress['percent'] . '%;"></div>
                            <span class="progress-text">' . $syncedGuestsProgress['text'] . '</span>
                        </div>
                    </td>
                    <td class="scope-label" style="display: block;">' . $syncedGuestsProgress['button'] . '</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">' . $helper->__('Synced customers') . '</td>
                    <td class="value">
                        <div class="progress">
                            <div class="progress-bar" style="width:' . $syncedCustomersProgress['percent'] . '%;"></div>
                            <span class="progress-text">' . $syncedCustomersProgress['text'] . '</span>
                        </div>
                    </td>
                    <td class="scope-label" style="display: block;">' . $syncedCustomersProgress['button'] . '</td>
                    <td></td>
                </tr>
            </table>';

        return $html;
    }

    /**
     * Get last synced datetime
     *
     * @return string
     */
    protected function _getLastSyncedText()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        $lastSynced = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->setPageSize(1)
            ->setCurPage(1)
            ->setOrder('synced_at')
            ->load();
        if ($lastSynced && $lastSynced->getFirstItem()) {
            $lastSynced = $lastSynced->getFirstItem()->getSyncedAt();
            $lastSyncedText = Mage::helper('core')->formatDate($lastSynced, 'medium', true);
        } else {
            $lastSyncedText = $helper->__('Not synced yet');
        }

        return $lastSyncedText;
    }

    /**
     * Get synced guests progress
     *
     * @return array
     */
    protected function _getSyncedGuestsProgress()
    {
        $result = array();
        $scopeStoreIds = $this->_getScopeStoreIds();
        $totalGuests = Mage::getModel('newsletter/subscriber')->getCollection()
            ->addFieldToFilter('type', Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_GUEST_TYPE)
            ->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
        if (count($scopeStoreIds) > 0) {
            $totalGuests->addFieldToFilter('store_id', $scopeStoreIds);
        } else {
            $totalGuests->addFieldToFilter('store_id', array('neq' => '0'));
        }
        $totalGuests = $totalGuests->getSize();

        $syncedGuests = Mage::getModel('newsletter/subscriber')->getCollection()
            ->addFieldToFilter('type', Mailigen_Synchronizer_Helper_Customer::SUBSCRIBER_GUEST_TYPE)
            ->addFieldToFilter('subscriber_status', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->addFieldToFilter('mailigen_synced', 1);
        if (count($scopeStoreIds) > 0) {
            $syncedGuests->addFieldToFilter('store_id', $scopeStoreIds);
        } else {
            $syncedGuests->addFieldToFilter('store_id', array('neq' => '0'));
        }
        $syncedGuests = $syncedGuests->getSize();

        $result['percent'] = round($syncedGuests / $totalGuests * 100, 2);
        $result['text'] = "{$result['percent']}% ($syncedGuests/$totalGuests)";
        $result['button'] = ($this->_getSchedule()->getLastRunningJob() === false) ? $this->_getResetGuestsSyncButton() : '';

        return $result;
    }


    /**
     * Get synced customers progress
     *
     * @return array
     */
    protected function _getSyncedCustomersProgress()
    {
        $scopeStoreIds = $this->_getScopeStoreIds();
        $totalCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection();
        if (count($scopeStoreIds) > 0) {
            $totalCustomers->addFieldToFilter('store_id', $scopeStoreIds);
        } else {
            $totalCustomers->addFieldToFilter('store_id', array('neq' => '0'));
        }
        $totalCustomers = $totalCustomers->getSize();

        if ($totalCustomers === 0) {
            $totalCustomers = Mage::getModel('customer/customer')->getCollection();
            if (count($scopeStoreIds) > 0) {
                $totalCustomers->addFieldToFilter('store_id', $scopeStoreIds);
            }
            $totalCustomers = $totalCustomers->getSize();
        }

        $syncedCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->addFieldToFilter('is_synced', 1);
        if (count($scopeStoreIds) > 0) {
            $syncedCustomers->addFieldToFilter('store_id', $scopeStoreIds);
        } else {
            $syncedCustomers->addFieldToFilter('store_id', array('neq' => '0'));
        }
        $syncedCustomers = $syncedCustomers->getSize();

        $result['percent'] = round($syncedCustomers / $totalCustomers * 100, 2);
        $result['text'] = "{$result['percent']}% ($syncedCustomers/$totalCustomers)";
        $result['button'] = ($this->_getSchedule()->getLastRunningJob() === false) ? $this->_getResetCustomersSyncButton() : '';

        return $result;
    }

    /**
     * Get Sync status and show stop button
     *
     * @return string
     */
    protected function _getSyncStatusText()
    {
        if ($this->_getSchedule()->getLastRunningJob()) {
            $html = 'Running';
            if (strlen($this->_getSchedule()->getLastRunningJob()->getExecutedAt())) {
                $html .= ' (Started at: ';
                $html .= Mage::helper('core')->formatDate($this->_getSchedule()->getLastRunningJob()->getExecutedAt(), 'medium', true);
                $html .= ') ';

                /**
                 * Show stop sync button
                 */
                $html .= $this->_getStopSyncButton();
            }
        } elseif ($this->_getSchedule()->getLastPendingJob()) {
            $html = 'Pending';
            if (strlen($this->_getSchedule()->getLastPendingJob()->getScheduledAt())) {
                $html .= ' (Scheduled at: ';
                $html .= Mage::helper('core')->formatDate($this->_getSchedule()->getLastPendingJob()->getScheduledAt(), 'medium', true);
                $html .= ')';
            }
        } else {
            $html = 'Not scheduled';
            /**
             * Show reset sync customers button
             */
        }

        return $html;
    }

    /**
     * Get Stop sync button html
     *
     * @return string
     */
    protected function _getStopSyncButton()
    {
        $stopSyncUrl = Mage::helper('adminhtml')->getUrl('*/mailigen/stopSync');
        $buttonJs = '<script type="text/javascript">
            //<![CDATA[
            function stopMailigenSynchronizer() {
                new Ajax.Request("' . $stopSyncUrl . '", {
                    method: "get",
                    onSuccess: function(transport){
                        if (transport.responseText){
                            alert(transport.responseText);
                        }
                    }
                });
            }
            //]]>
            </script>';

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'id'      => 'stop_mailigen_synchronizer_button',
                    'label'   => $this->helper('adminhtml')->__('Stop Sync'),
                    'onclick' => 'javascript:stopMailigenSynchronizer(); return false;',
                )
            );

        return $buttonJs . $button->toHtml();
    }

    /**
     * Get Reset customers sync button html
     *
     * @return string
     */
    protected function _getResetCustomersSyncButton()
    {
        $resetSyncUrl = Mage::helper('adminhtml')->getUrl('*/mailigen/resetSyncCustomers');
        $buttonJs = '<script type="text/javascript">
            //<![CDATA[
            function resetCustomersSync() {
                new Ajax.Request("' . $resetSyncUrl . '", {
                    method: "get",
                    onSuccess: function(transport){
                        if (transport.responseText == "1"){
                            window.location.reload();
                        }
                        else {
                            alert(transport.responseText);
                        }
                    }
                });
            }
            //]]>
            </script>';

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'id'      => 'reset_customers_sync__button',
                    'label'   => $this->helper('adminhtml')->__('Reset Customers Sync'),
                    'onclick' => 'javascript:resetCustomersSync(); return false;',
                )
            );

        return $buttonJs . $button->toHtml();
    }

    /**
     * Get Reset newsletter sync button html
     *
     * @return string
     */
    protected function _getResetGuestsSyncButton()
    {
        $resetSyncUrl = Mage::helper('adminhtml')->getUrl('*/mailigen/resetSyncNewsletter');
        $buttonJs = '<script type="text/javascript">
            //<![CDATA[
            function resetNewsletterSync() {
                new Ajax.Request("' . $resetSyncUrl . '", {
                    method: "get",
                    onSuccess: function(transport){
                        if (transport.responseText == "1"){
                            window.location.reload();
                        }
                        else {
                            alert(transport.responseText);
                        }
                    }
                });
            }
            //]]>
            </script>';

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(
                array(
                    'id'      => 'reset_newsletter_sync__button',
                    'label'   => $this->helper('adminhtml')->__('Reset Guests Sync'),
                    'onclick' => 'javascript:resetNewsletterSync(); return false;',
                )
            );

        return $buttonJs . $button->toHtml();
    }

    /**
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _getScopeStoreIds()
    {
        $storeIds = array();
        $storeCode = Mage::getSingleton('adminhtml/config_data')->getStore();
        $websiteCode = Mage::getSingleton('adminhtml/config_data')->getWebsite();

        if ($storeCode !== '') {
            // store level scope
            $storeIds[] = Mage::getModel('core/store')->load($storeCode)->getId();
        } elseif ($websiteCode !== '') {
            // website level scope
            $websiteId = Mage::getModel('core/website')->load($websiteCode)->getId();
            $storeIds = Mage::app()->getWebsite($websiteId)->getStoreIds();
        }

        return $storeIds;
    }

    /**
     * @return Mailigen_Synchronizer_Model_Schedule
     */
    protected function _getSchedule()
    {
        return Mage::getSingleton('mailigen_synchronizer/schedule');
    }
}