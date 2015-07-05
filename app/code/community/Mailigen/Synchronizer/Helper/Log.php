<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Helper_Log extends Mage_Core_Helper_Abstract
{
    const LOG_FILE = 'mailigen_synchronizer.log';
    const WEBHOOK_FILE = 'mailigen_webhooks.log';

    /**
     * @param      $message
     * @param null $level
     */
    public static function log($message, $level = null)
    {
        Mage::log($message, $level, self::LOG_FILE);
    }

    /**
     * @param Exception $e
     */
    public static function logException(Exception $e)
    {
        self::log("\n" . $e->__toString(), Zend_Log::ERR);
    }

    /**
     * @param      $message
     * @param null $level
     */
    public function logWebhook($message, $level = null)
    {
        Mage::log($message, $level, self::WEBHOOK_FILE);
    }
}