<?php
namespace MalibuCommerce\MConnect\Model\Cron;


class Queue
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Queue\Collection
     */
    protected $mConnectResourceQueueCollection;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Resource\Queue\Collection $mConnectResourceQueueCollection
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectResourceQueueCollection = $mConnectResourceQueueCollection;
    }
    public function process()
    {
        $config = $this->mConnectConfig;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $queues = $this->mConnectResourceQueueCollection->addFieldToFilter('status', \MalibuCommerce\Mconnect\Model\Queue::STATUS_PENDING);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue need processing.';
        }
        foreach ($queues as $queue) {
            $queue->process();
        }
        return sprintf('Processed %d item(s) in queue.', $count);
    }

    public function clean()
    {
        $config = $this->mConnectConfig;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $value = $config->get('queue/delete_after');
        if (!$value) {
            return 'Queue cleaning not enabled.';
        }
        $queues = $this->mConnectResourceQueueCollection->olderThanDays($value);
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
        $config = $this->mConnectConfig;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }
        $value = $config->get('queue/error_after');
        if (!$value) {
            return 'Error marking not enabled.';
        }
        $queues = $this->mConnectResourceQueueCollection->olderThanMinutes($value)
            ->addFieldToFilter('status', MalibuCommerce_Mconnect_Model_Queue::STATUS_RUNNING)
        ;
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to remove.';
        }
        foreach ($queues as $queue) {
            $queue->setStatus(MalibuCommerce_Mconnect_Model_Queue::STATUS_ERROR)->save();
        }
        return sprintf('Marked %d item(s) in queue.', $count);
    }
}
