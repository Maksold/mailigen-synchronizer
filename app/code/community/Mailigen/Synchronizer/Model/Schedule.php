<?php

/**
 * Mailigen_Synchronizer
 *
 * @category    Mailigen
 * @package     Mailigen_Synchronizer
 * @author      Maksim Soldatjonok <maksold@gmail.com>
 */
class Mailigen_Synchronizer_Model_Schedule extends Mage_Core_Model_Abstract
{
    /**
     * @var string
     */
    protected $_jobCode = 'mailigen_synchronizer';

    /**
     * @var null
     */
    protected $_countPendingOrRunningJobs = null;

    /**
     * @return int|null
     */
    public function countPendingOrRunningJobs()
    {
        if (is_null($this->_countPendingOrRunningJobs)) {
            $pendingOrRunningJobs = Mage::getModel('cron/schedule')->getCollection()
                ->addFieldToFilter('job_code', $this->_jobCode)
                ->addFieldToFilter('status', array(
                    'in' => array(
                        Mage_Cron_Model_Schedule::STATUS_RUNNING,
                        Mage_Cron_Model_Schedule::STATUS_PENDING
                    )
                ));
            $this->_countPendingOrRunningJobs = $pendingOrRunningJobs->getSize();
        }

        return $this->_countPendingOrRunningJobs;
    }

    /**
     * @return null|Mage_Cron_Model_Schedule
     */
    public function getLastRunningJob()
    {
        /** @var $runningJobs Mage_Cron_Model_Resource_Schedule_Collection */
        $runningJobs = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('job_code', $this->_jobCode)
            ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_RUNNING)
            ->setOrder('executed_at')
            ->setPageSize(1)->setCurPage(1);

        return $runningJobs->getSize() ? $runningJobs->getFirstItem() : null;
    }

    /**
     * @return null|Mage_Cron_Model_Schedule
     */
    public function getLastPendingJob()
    {
        /** @var $runningJobs Mage_Cron_Model_Resource_Schedule_Collection */
        $pendingJobs = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToFilter('job_code', $this->_jobCode)
            ->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->setOrder('executed_at')
            ->setPageSize(1)->setCurPage(1);

        return $pendingJobs->getSize() ? $pendingJobs->getFirstItem() : null;
    }

    /**
     * @return Mage_Cron_Model_Schedule
     */
    public function createJob()
    {
        /** @var $cronScheduler Mage_Cron_Model_Schedule */
        $cronScheduler = Mage::getModel('cron/schedule');
        $cronScheduler->setJobCode($this->_jobCode)
            ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', time()))
            ->setScheduledAt(strftime('%Y-%m-%d %H:%M:00', time()));

        return $cronScheduler->save();
    }
}