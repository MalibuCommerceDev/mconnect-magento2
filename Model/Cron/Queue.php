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
    protected $queueCollectionFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * Queue constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\Config                           $config
     * @param \MalibuCommerce\MConnect\Model\Resource\Queue\CollectionFactory $queueCollectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                     $date
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Resource\Queue\CollectionFactory $queueCollectionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        $this->config = $config;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->date = $date;
    }

    public function process()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'Module is disabled.';
        }

        /**
         * Make sure to process only those queue items with where action and code not matching any running queue items
         */
        $queues = $this->queueCollectionFactory->create();
        $queues->getSelect()->reset();
        $queues->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->joinLeft(
                ['q2' => 'malibucommerce_mconnect_queue'],
                $queues->getConnection()->quoteInto('q1.code = q2.code AND q1.action = q2.action AND q2.status = ?', QueueModel::STATUS_RUNNING),
                []
            )
            ->where('q1.status = ?', QueueModel::STATUS_PENDING)
            ->where('q2.id IS NULL');

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

        $queues = $this->queueCollectionFactory->create();
        $queues = $queues->olderThanDays($value, $this->date);
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

        $queues = $this->queueCollectionFactory->create();
        $queues = $queues->olderThanMinutes($value, $this->date)
            ->addFieldToFilter('status', ['eq' => QueueModel::STATUS_RUNNING]);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to remove.';
        }

        foreach ($queues as $queue) {
            $message = sprintf("Marked as staled after %s minutes\n\n", $value);
            $message .= $queue->getMessage();

            $queue->setStatus(QueueModel::STATUS_ERROR)
                ->setMessage($message)
                ->save();
        }

        return sprintf('Marked %d item(s) in queue.', $count);
    }
}
