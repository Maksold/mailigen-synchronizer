<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Resource_Iterator_Batched extends Varien_Object
{
    const DEFAULT_BATCH_SIZE = 250;

    /**
     * @var null
     */
    protected $_collectionCount = null;

    /**
     * @var int
     */
    protected $_batchSize = self::DEFAULT_BATCH_SIZE;

    /**
     * @param       $collection
     * @param array $callbackForIndividual
     * @param array $callbackAfterBatch
     * @param null  $batchSize
     * @param null  $batchLimit
     * @return int
     */
    public function walk($collection, array $callbackForIndividual, array $callbackAfterBatch, $batchSize = null, $batchLimit = null)
    {
        if (!is_null($batchSize)) {
            $this->_batchSize = $batchSize;
        }

        $this->_collectionCount = $collection->getSelectCountSql();

        $collection->setPageSize($this->_batchSize);

        $currentPage = 1;
        $origCurrentPage = 1;
        $pages = $this->_getPagesSize();
        $origPages = $collection->getLastPageNumber();

        do {
            $collection->clear();
            $collection->setCurPage($currentPage);
            $collection->load();

            foreach ($collection as $item) {
                call_user_func($callbackForIndividual, $item);
            }

            if (!empty($callbackAfterBatch)) {
                $collectionInfo = array('currentPage' => $origCurrentPage, 'pages' => $origPages, 'pageSize' => $this->_batchSize);
                call_user_func($callbackAfterBatch, $collectionInfo);
            }

            if (is_int($batchLimit)) {
                $batchLimit -= $this->_batchSize;
                if ($batchLimit <= 0) {
                    return 0;
                }
            }

            $origCurrentPage++;
            $this->_recalcPages($currentPage, $pages);
        } while ($currentPage <= $pages);

        return 1;
    }

    /**
     * @return float
     */
    protected function _getPagesSize()
    {
        $count = $this->_collectionCount->query()->fetchColumn();
        return ceil($count/$this->_batchSize);
    }

    /**
     * @param $currentPage
     * @param $pages
     */
    protected function _recalcPages(&$currentPage, &$pages)
    {
        $_pages = $this->_getPagesSize();
        $pagesDiff = $_pages - $pages;
        if ($pagesDiff < 0) {
            $pages = $_pages;
            $currentPage += $pagesDiff;
        }
        elseif ($pagesDiff >= 0) {
            $currentPage++;
        }

        if ($currentPage <= 0) {
            $currentPage = 1;
        }
    }
}