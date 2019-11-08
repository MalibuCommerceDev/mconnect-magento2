<?php

namespace MalibuCommerce\MConnect\Model\Cron;

use \MalibuCommerce\Mconnect\Model\Queue as QueueModel;
use \MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;

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

    public function process($forceSyncNow = false)
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        /**
         * Make sure to process only those queue items with where action and code not matching any running queue items per website
         */
        $queues = $this->queueCollectionFactory->create();
        $queues->getSelect()->reset();
        $queues->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->joinLeft(
                ['q2' => 'malibucommerce_mconnect_queue'],
                $queues->getConnection()->quoteInto('q1.code = q2.code AND q1.action = q2.action AND q1.website_id = q2.website_id AND q2.status = ?', QueueModel::STATUS_RUNNING),
                []
            )
            ->where('q1.status = ?', QueueModel::STATUS_PENDING)
            ->where('q2.id IS NULL');

        if (!$forceSyncNow || ($forceSyncNow instanceof \Magento\Cron\Model\Schedule)) {
            $queues->getSelect()->where('q1.scheduled_at <= ?', $this->date->gmtDate());
        }

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
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        $value = $config->get('queue/delete_after');
        if (!$value) {
            return 'Queue cleaning is not enabled.';
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
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        $value = $config->get('queue/error_after');
        if (!$value) {
            return 'Mconnect module is not configured, queue/error_after is missing.';
        }

        $queues = $this->queueCollectionFactory->create();
        $queues = $queues->olderThanMinutes($value, $this->date)
            ->addFieldToFilter('status', ['eq' => QueueModel::STATUS_RUNNING]);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to mark with error status.';
        }

        foreach ($queues as $queue) {
            $message = sprintf("Marked as staled after %s minutes\n\n", $value);
            $message .= $queue->getMessage();
            $message = mb_strimwidth(
                $message,
                0,
                \MalibuCommerce\MConnect\Helper\Data::QUEUE_ITEM_MAX_MESSAGE_SIZE,
                '...'
            );

            $queue->setStatus(QueueModel::STATUS_ERROR)
                ->setMessage($message)
                ->save();
        }

        return sprintf('Marked %d item(s) in queue.', $count);
    }

    public function resyncErrorOrders()
    {
        $config         = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }
        $maxRetryAmount = $config->get('order/export_retry_amount');
        if (!$maxRetryAmount) {
            return 'Max amount of retry is not set.';
        }

        $queues = $this->queueCollectionFactory->create();
        $queues = $queues->addFieldToFilter('status', ['eq' => QueueModel::STATUS_ERROR])
            ->addFieldToFilter('code', ['eq' => OrderModel::CODE])
            ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
            ->addFieldToFilter('retry_count', ['lt' => $maxRetryAmount]);
        $count = $queues->getSize();
        if (!$count) {
            return 'No items in queue to mark with error status.';
        }

        foreach ($queues as $queue) {
            $queue->process();
            $queue->setRetryCount($queue->getRetryCount() + 1)
                ->save();
        }

        return sprintf('Resync %d order(s) in queue.', $count);
    }
}
