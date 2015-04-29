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
     * @param       $collection
     * @param array $callbackForIndividual
     * @param array $callbackAfterBatch
     * @param null  $batchSize
     * @param null  $batchLimit
     * @return int
     */
    public function walk($collection, array $callbackForIndividual, array $callbackAfterBatch, $batchSize = null, $batchLimit = null)
    {
        if (!$batchSize) {
            $batchSize = self::DEFAULT_BATCH_SIZE;
        }

        $collection->setPageSize($batchSize);

        $currentPage = 1;
        $pages = $collection->getLastPageNumber();

        do {
            $collection->setCurPage($currentPage);
            $collection->load();

            foreach ($collection as $item) {
                call_user_func($callbackForIndividual, $item);
            }

            if (!empty($callbackAfterBatch)) {
                $collectionInfo = array('currentPage' => $currentPage, 'pages' => $pages, 'pageSize' => $batchSize);
                call_user_func($callbackAfterBatch, $collectionInfo);
            }

            if (is_int($batchLimit) && $currentPage * $batchSize >= $batchLimit) {
                return 0;
            }

            $currentPage++;
            $collection->clear();
        } while ($currentPage <= $pages);

        return 1;
    }
}