<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_Adminhtml_Newsletter_Webhooks
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

        $webhooksUrl = Mage::app()->getStore($storeId)->getBaseUrl() . 'mailigen/webhook/';
        $webhooksSetupImg = $this->getSkinUrl('mailigen_synchronizer/Mailigen_webhooks_setup_instruction.jpg');
        $webhooksSetupText = $helper->__('Mailigen webhooks setup instruction');

        $html = '<table cellspacing="0" class="form-list">
                <tr>
                    <td class="label">' . $helper->__('Mailigen Webhooks URL') . '</td>
                    <td class="value with-tooltip">
                        <a href="' . $webhooksUrl . '" target="_blank">' . $webhooksUrl . '</a>
                        <div class="field-tooltip"><div>
                            <img src="' . $webhooksSetupImg . '" alt="' . $webhooksSetupText . '" title="' . $webhooksSetupText . '" style="width:800px;"/>
                        </div></div>
                        <p class="note"><span>' . $helper->__('This URL you should use in Mailigen, to configure webhooks.') . '</span></p>
                    </td>
                    <td class="scope-label"></td>
                    <td></td>
                </tr>
            </table>';

        return $html;
    }
}