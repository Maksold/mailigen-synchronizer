<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_Adminhtml_Newsletter_Secretkey
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
        $storeId = $helper->getScopeStoreId();

        $webhooksSecretKey = $helper->getWebhooksSecretKey($storeId);
        if (empty($webhooksSecretKey)) {
            $webhooksSecretKey = $helper->generateWebhooksSecretKey($storeId);
        }

        $html = '<table cellspacing="0" class="form-list">
                <tr>
                    <td class="label">' . $helper->__('Mailigen Webhooks Secret Key') . '</td>
                    <td class="value">
                        <span id="webhooks_secret_key">' . $webhooksSecretKey . '</span>
                    </td>
                    <td class="scope-label">' . $this->_getGenerateButton() . '</td>
                    <td></td>
                </tr>
            </table>';

        return $html;
    }

    /**
     * Get generate webhooks secret key button html
     *
     * @return string
     */
    protected function _getGenerateButton()
    {
        /** @var $helper Mailigen_Synchronizer_Helper_Data */
        $helper = Mage::helper('mailigen_synchronizer');

        $generateWebhooksSecretKeyUrl = Mage::helper('adminhtml')->getUrl('*/mailigen/generateSecretKey', array(
            'storeId' => $helper->getScopeStoreId()
        ));
        $buttonJs = '<script type="text/javascript">
            //<![CDATA[
            function generateWebhooksSecretKey() {
                new Ajax.Request("' . $generateWebhooksSecretKeyUrl . '", {
                    method: "get",
                    onSuccess: function(transport){
                        if (transport.responseText){
                            $("webhooks_secret_key").update(transport.responseText);
                        }
                    }
                });
            }
            //]]>
            </script>';

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id' => 'generate_webhooks_secret_key_button',
                'label' => $this->helper('adminhtml')->__('Generate New Secret Key'),
                'onclick' => 'javascript:generateWebhooksSecretKey(); return false;'
            ));

        return $buttonJs . $button->toHtml();
    }
}