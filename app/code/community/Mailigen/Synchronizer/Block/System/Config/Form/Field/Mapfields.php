<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Block_System_Config_Form_Field_Mapfields extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected $_customerAttributes = null;
    protected $_additionalAttributes = null;

    public function __construct()
    {
        $this->addColumn(
            'mailigen', array(
                'label' => $this->h()->__('Mailigen merge field'),
                'style' => 'width:150px',
            )
        );
        $this->addColumn(
            'magento', array(
                'label' => $this->h()->__('Magento Customer or Additional attributes'),
                'style' => 'width:120px',
            )
        );

        $this->_addAfter = false;
        parent::__construct();
        $this->setTemplate('mailigen_synchronizer/system/config/form/field/mapfields.phtml');

        $this->_getCustomerAttributes();

        $this->_getAdditionalAttributes();
    }

    /**
     * @return array|null
     */
    protected function _getCustomerAttributes()
    {
        if ($this->_customerAttributes === null) {

            $this->_customerAttributes = array();
            $attrSetId = Mage::getResourceModel('eav/entity_attribute_collection')
                ->setEntityTypeFilter(1)
                ->addSetInfo()
                ->getData();

            foreach ($attrSetId as $option) {
                if ($option['frontend_label']) {
                    $this->_customerAttributes[$option['attribute_id']] = $option['frontend_label'];
                }
            }

            ksort($this->_customerAttributes);
        }

        return $this->_customerAttributes;
    }

    /**
     * @return array|null
     */
    protected function _getAdditionalAttributes()
    {
        if ($this->_additionalAttributes === null) {

            $this->_additionalAttributes = array();
            $scopeArray = $this->h()->getCurrentScope();
            $mapFields = $this->h()->getAdditionalMergeFieldsSerialized($scopeArray['scope_id'], $scopeArray['scope']);
            $customFieldTypes = unserialize($mapFields);

            if (is_array($customFieldTypes)) {
                foreach ($customFieldTypes as $customFieldType) {
                    $label = $customFieldType['label'];
                    $value = $customFieldType['value'];
                    $this->_additionalAttributes[$value] = $label;
                }
            }

            ksort($this->_additionalAttributes);
        }

        return $this->_additionalAttributes;
    }

    /**
     * @param string $columnName
     * @return string
     * @throws Exception
     */
    protected function _renderCellTemplate($columnName)
    {
        if (empty($this->_columns[$columnName])) {
            throw new Exception('Wrong column name specified.');
        }

        $column = $this->_columns[$columnName];
        $inputName = $this->getElement()->getName() . '[#{_id}][' . $columnName . ']';

        if ($columnName === 'magento') {
            $rendered = '<select name="' . $inputName . '">';

            if (is_array($this->_getCustomerAttributes()) && count($this->_getCustomerAttributes())) {
                $rendered .= '<optgroup label="' . $this->h()->__('Customer attributes') . '">';
                foreach ($this->_getCustomerAttributes() as $att => $name) {
                    $rendered .= '<option value="' . $att . '">' . $name . '</option>';
                }
                $rendered .= '</optgroup>';
            }

            if (is_array($this->_getAdditionalAttributes()) && count($this->_getAdditionalAttributes())) {
                $rendered .= '<optgroup label="' . $this->h()->__('Additional attributes') . '">';
                foreach ($this->_getAdditionalAttributes() as $att => $name) {
                    $rendered .= '<option value="' . $att . '">' . $name . '</option>';
                }
                $rendered .= '</optgroup>';
            }

            $rendered .= '</select>';
        } else {
            return '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' . ($column['size'] ? 'size="' . $column['size'] . '"' : '') . '/>';
        }

        return $rendered;
    }

    /**
     * @return Mailigen_Synchronizer_Helper_Data
     */
    public function h()
    {
        return Mage::helper('mailigen_synchronizer');
    }
}
