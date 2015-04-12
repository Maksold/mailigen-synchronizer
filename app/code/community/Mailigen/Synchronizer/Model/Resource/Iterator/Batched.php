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
     */
    public function walk($collection, array $callbackForIndividual, array $callbackAfterBatch, $batchSize = null)
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
                call_user_func($callbackAfterBatch);
            }

            $currentPage++;
            $collection->clear();
        } while ($currentPage <= $pages);
    }
}