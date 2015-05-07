<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_Adminhtml_Sync_Information
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
        $syncedCustomersProgress = $this->_getSyncedCustomersProgress();
        $syncStatusText = $this->_getSyncStatusText();

        $html = '<style type="text/css">
            .progress {
              padding: 2px;
              background: rgba(0, 0, 0, 0.25);
              border-radius: 6px;
              -webkit-box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25), 0 1px rgba(255, 255, 255, 0.08);
              box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.25), 0 1px rgba(255, 255, 255, 0.08);
            }
            .progress-bar {
              font-size: 12px;
              color: #111;
              text-align: left;
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
            </style>
            <table cellspacing="0" class="form-list">
                <tr>
                    <td class="label">' . $helper->__('Last Synced') . '</td>
                    <td class="value">' . $lastSyncedText . '</td>
                    <td class="scope-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">' . $helper->__('Synced Customers') . '</td>
                    <td class="value">
                        <div class="progress">
                            <div class="progress-bar" style="width:' . $syncedCustomersProgress['percent'] . '%;">
                            ' .  $syncedCustomersProgress['text'] . '
                            </div>
                        </div>
                    </td>
                    <td class="scope-label"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="label">' . $helper->__('Sync Status') . '</td>
                    <td class="value">' . $syncStatusText . '</td>
                    <td class="scope-label"></td>
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
//            $lastSyncedText = $helper->time_elapsed_string($lastSynced, true);
            $lastSyncedText = Mage::helper('core')->formatDate($lastSynced, 'medium', true);
        } else {
            $lastSyncedText = $helper->__('Not synced yet');
        }

        return $lastSyncedText;
    }

    /**
     * Get synced customers progress
     *
     * @return array
     */
    protected function _getSyncedCustomersProgress()
    {
        $totalCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection()->getSize();
        $syncedCustomers = Mage::getModel('mailigen_synchronizer/customer')->getCollection()
            ->addFieldToFilter('is_synced', 1)
            ->getSize();
        $syncedCustomersPercent = round($syncedCustomers / $totalCustomers * 100);
        $syncedCustomersText = "$syncedCustomersPercent% ($syncedCustomers/$totalCustomers)";

        return array('percent' => $syncedCustomersPercent, 'text' => $syncedCustomersText);
    }

    /**
     * Get Sync status and show stop button
     *
     * @return string
     */
    protected function _getSyncStatusText()
    {
        /** @var $mailigenSchedule Mailigen_Synchronizer_Model_Schedule */
        $mailigenSchedule = Mage::getModel('mailigen_synchronizer/schedule');
        $runningJob = $mailigenSchedule->getLastRunningJob();
        $pendingJob = $mailigenSchedule->getLastPendingJob();

        if ($runningJob) {
            $html = "Running";
            if (strlen($runningJob->getExecutedAt())) {
                $html .= ' (Started at: ';
                $html .= Mage::helper('core')->formatDate($runningJob->getExecutedAt(), 'medium', true);
                $html .= ') ';

                /**
                 * Show stop sync customers button
                 */
                $html .= $this->_getStopCustomersSyncButton();
            }
        }
        elseif ($pendingJob) {
            $html = "Pending";
            if (strlen($pendingJob->getScheduledAt())) {
                $html .= ' (Scheduled at: ';
                $html .= Mage::helper('core')->formatDate($pendingJob->getScheduledAt(), 'medium', true);
                $html .= ')';
            }
        }
        else {
            $html = "Not scheduled";
            /**
             * Show reset sync customers button
             */
            $html .= ' '.$this->_getResetCustomersSyncButton();
        }

        return $html;
    }

    /**
     * Get Stop customers sync button html
     *
     * @return string
     */
    protected function _getStopCustomersSyncButton()
    {
        $stopSyncUrl = Mage::helper('adminhtml')->getUrl('*/mailigen/stopSyncCustomers');
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
            ->setData(array(
                'id' => 'stop_mailigen_synchronizer_button',
                'label' => $this->helper('adminhtml')->__('Stop sync'),
                'onclick' => 'javascript:stopMailigenSynchronizer(); return false;'
            ));

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
            function resetMailigenSynchronizer() {
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
            ->setData(array(
                'id' => 'reset_mailigen_synchronizer_button',
                'label' => $this->helper('adminhtml')->__('Reset sync'),
                'onclick' => 'javascript:resetMailigenSynchronizer(); return false;'
            ));

        return $buttonJs . $button->toHtml();
    }
}