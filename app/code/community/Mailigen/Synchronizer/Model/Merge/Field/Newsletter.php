<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Merge_Field_Newsletter extends Mailigen_Synchronizer_Model_Merge_Field_Abstract
{
    /**
     * @return null|string
     * @throws Mage_Core_Exception
     */
    public function getListId()
    {
        $listId = $this->h()->getNewsletterContactList($this->getStoreId());
        if (empty($listId)) {
            Mage::throwException('Newsletter contact list isn\'t selected');
        }

        return $listId;
    }

    /**
     * @return array
     */
    protected function _getMergeFields()
    {
        return array(
            'WEBSITEID'      => array(
                'title'      => 'Website id',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'NEWSLETTERTYPE' => array(
                'title'      => 'Type',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'STOREID'        => array(
                'title'      => 'Store id',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
            'STORELANGUAGE'  => array(
                'title'      => 'Store language',
                'field_type' => 'text',
                'req'        => false,
                'public'     => false,
            ),
        );
    }
}