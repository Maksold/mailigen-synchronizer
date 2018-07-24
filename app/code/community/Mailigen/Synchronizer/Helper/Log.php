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
    const MAIN_LOG_FILE                 = 'mailigen_synchronizer.log';
    const MAIN_EXCEPTION_LOG_FILE       = 'mailigen_synchronizer_exception.log';
    const SYNC_LOG_FILE                 = 'mailigen_synchronizer_sync.log';
    const SYNC_EXCEPTION_LOG_FILE       = 'mailigen_synchronizer_sync_exception.log';
    const WEBHOOK_LOG_FILE              = 'mailigen_synchronizer_webhook.log';
    const WEBHOOK_EXCEPTION_LOG_FILE    = 'mailigen_synchronizer_webhook_exception.log';

    /**
     * @var string
     */
    protected $_logFile = null;

    /**
     * Jr_ImportHorizon_Helper_Log constructor.
     */
    public function __construct()
    {
        $this->setLogFile(self::MAIN_LOG_FILE);
    }

    /**
     * @param string $logFile
     * @return $this
     */
    public function setLogFile($logFile)
    {
        $this->_logFile = $logFile;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogFile()
    {
        return $this->_logFile;
    }

    /**
     * @param      $message
     * @param null $level
     */
    public function log($message, $level = null)
    {
        Mage::log($message, $level, $this->getLogFile());
    }

    /**
     * @param Exception $e
     */
    public function logException(Exception $e)
    {
        $this->log("\nException: " . $e->getMessage() . "\n", Zend_Log::ERR);

        $logFile = $this->getLogFile();
        switch ($logFile) {
            case self::WEBHOOK_LOG_FILE:
                $this->setLogFile(self::WEBHOOK_EXCEPTION_LOG_FILE);
                break;
            case self::MAIN_LOG_FILE:
            default:
                $this->setLogFile(self::MAIN_EXCEPTION_LOG_FILE);
                break;
        }

        $this->log("\n" . $e->__toString() . "\n" . $this->getExceptionTraceAsString($e) . "\n", Zend_Log::ERR);

        $this->setLogFile($logFile);
    }

    /**
     * @param $exception
     * @return string
     */
    public function getExceptionTraceAsString(Exception $exception)
    {
        $result = '';
        $count = 0;
        foreach ($exception->getTrace() as $frame) {
            $args = "";
            if (isset($frame['args'])) {
                $args = array();
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = "Array";
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? "true" : "false";
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(", ", $args);
            }
            $result .= sprintf("#%s %s(%s): %s%s(%s)\n",
                $count,
                isset($frame['file']) ? $frame['file'] : '',
                isset($frame['line']) ? $frame['line'] : '',
                isset($frame['class']) ? $frame['class'] . '->' : '',
                isset($frame['function']) ? $frame['function'] : '',
                $args);
            $count++;
        }

        return $result;
    }
}