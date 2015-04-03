<?php 

class Mailigen_Synchronizer_Block_Newsletter_Subscriber_Grid extends Mage_Adminhtml_Block_Newsletter_Subscriber_Grid
{
	protected function _prepareLayout()
    {

        $this->setChild('sync_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label'     => Mage::helper('adminhtml')->__('Bulk synchronize with Mailigen'),
                    'onclick'   => "setLocation('{$this->getUrl('*/mailigen/sync')}')",
                    'class'   => 'task'
                ))
        );

        return parent::_prepareLayout();
    }

    public function getSyncButtonHtml()
    {
        return $this->getChildHtml('sync_button');
    }

    public function getMainButtonsHtml()
    {
        $html = parent::getMainButtonsHtml();

        $enabled = Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_status');

        if( $enabled ) {
            $html.= $this->getSyncButtonHtml();
        }

        return $html;
    }
}