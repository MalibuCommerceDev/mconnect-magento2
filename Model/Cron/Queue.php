<?php

namespace MalibuCommerce\MConnect\Model\Cron;

use \MalibuCommerce\Mconnect\Model\Queue as QueueModel;

class Queue
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Queue\Collection
     */
    protected $queueCollection;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Resource\Queue\Collection $queueCollection,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        $this->config = $config;
        $this->queueCollection = $queueCollection;
        $this->date = $date;
    }

    public function process()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $queues = $this->queueCollection->addFieldToFilter('status', QueueModel::STATUS_PENDING);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue need processing.';
        }

        /** @var \MalibuCommerce\MConnect\Model\Queue $queue */
        foreach ($queues as $queue) {
            $queue->process();
        }

        return sprintf('Processed %d item(s) in queue.', $count);
    }

    public function clean()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $value = $config->get('queue/delete_after');
        if (!$value) {
            return 'Queue cleaning not enabled.';
        }
        $queues = $this->queueCollection->olderThanDays($value, $this->date);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to remove.';
        }
        foreach ($queues as $queue) {
            $queue->delete();
        }

        return sprintf('Removed %d item(s) in queue.', $count);
    }

    public function error()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $value = $config->get('queue/error_after');
        if (!$value) {
            return 'Error marking not enabled.';
        }
        $queues = $this->queueCollection->olderThanMinutes($value, $this->date)
            ->addFieldToFilter('status', QueueModel::STATUS_RUNNING);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to remove.';
        }

        $gmtDate = $this->date->gmtDate();
        $currentDate = date('Y-m-d H:i:s');
        foreach ($queues as $queue) {
            $message = sprintf( "Marked as staled after %s minutes\n", $value);
            $message .= 'Created At in UTC: ' . $queue->getCreatedAt() . "\n";
            $message .= 'GMT Date: ' . $gmtDate . "\n";
            $message .= 'Current Date: ' . $currentDate;

            $queue->setStatus(QueueModel::STATUS_ERROR)
                ->setMessage($message)
                ->save();
        }

        return sprintf('Marked %d item(s) in queue.', $count);
    }
}
