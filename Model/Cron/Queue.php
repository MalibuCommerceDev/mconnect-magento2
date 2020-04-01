<?php

namespace MalibuCommerce\MConnect\Model\Cron;

use \MalibuCommerce\Mconnect\Model\Queue as QueueModel;
use \MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;
use \MalibuCommerce\MConnect\Model\Queue\Customer as CustomerModel;

class Queue
{
    const FLAG_ORDERS_EXPORT_LAST_RUN_TIME    = 'malibucommerce_mconnect_orders_export_last_run_time';
    const FLAG_CUSTOMERS_EXPORT_LAST_RUN_TIME = 'malibucommerce_mconnect_customers_export_last_run_time';

    /** @var \MalibuCommerce\MConnect\Model\Config */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection
     */
    protected $queueCollectionFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Mail
     */
    protected $mConnectMailer;

    /** @var \Magento\Sales\Model\OrderFactory */
    protected $salesOrderFactory;

    /** @var \MalibuCommerce\MConnect\Model\FlagFactory */
    protected $flagFactory;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \MalibuCommerce\MConnect\Helper\Mail $mConnectMailer,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \MalibuCommerce\MConnect\Model\FlagFactory $flagFactory
    ) {
        $this->config = $config;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->date = $date;
        $this->mConnectMailer = $mConnectMailer;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->flagFactory = $flagFactory;
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
                $queues->getConnection()->quoteInto(
                    'q1.code = q2.code AND q1.action = q2.action AND q1.website_id = q2.website_id AND q2.status = ?',
                    QueueModel::STATUS_RUNNING
                ),
                []
            )
            ->where('q1.code NOT IN (?)', [OrderModel::CODE, CustomerModel::CODE])
            ->where('q1.status = ?', QueueModel::STATUS_PENDING)
            ->where('q2.id IS NULL');

        if (!$forceSyncNow || ($forceSyncNow instanceof \Magento\Cron\Model\Schedule)) {
            $queues->getSelect()->where('q1.scheduled_at <= ?', $this->date->gmtDate());
        }

        $items = 0;
        /** @var \MalibuCommerce\MConnect\Model\Queue $queue */
        foreach ($queues as $queue) {
            if (($queue->getCode() == CustomerModel::CODE) && ($queue->getAction() == QueueModel::ACTION_EXPORT)) {
                continue;
            }

            $items++;
            $queue->process();
        }

        if ($items == 0) {
            return 'No items in queue need processing.';
        }

        return sprintf('Processed %d item(s) in queue.', $items);
    }

    public function processExportsOnly()
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
                $queues->getConnection()->quoteInto(
                    'q1.code = q2.code AND q1.action = q2.action AND q1.website_id = q2.website_id AND q2.status = ?',
                    QueueModel::STATUS_RUNNING
                ),
                []
            )
            ->where('q1.action = ?', QueueModel::ACTION_EXPORT)
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

    public function exportCustomers()
    {
        return $this->exportEntity(CustomerModel::CODE);
    }

    public function exportOrders()
    {
        return $this->exportEntity(OrderModel::CODE);
    }

    public function exportEntity($type)
    {
        if (!in_array($type, [OrderModel::CODE, CustomerModel::CODE])) {
            return 'Export for ' . $type. ' is not supported.';
        }

        $currentTime = time();
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        $lastProcessingTime = $this->getLastEntityExportTime($type);
        if ($lastProcessingTime && $config->getScheduledEntityExportDelayTime($type) > 0
            && ($currentTime - $lastProcessingTime) < $config->getScheduledEntityExportDelayTime($type)
        ) {

            return 'Execution postponed due to configured export delay between runs';
        }

        $lastProcessingTime = !$lastProcessingTime ? strtotime('12:00 AM') : $lastProcessingTime;
        $runTimes = $config->getScheduledEntityExportRunTimes($type);
        if (!$runTimes) {
            $runAllowed = true;
        } else {
            $runAllowed = false;
            foreach ($runTimes as $strTime) {
                $scheduledTime = strtotime($strTime);
                if ($currentTime >= $scheduledTime && $scheduledTime > $lastProcessingTime) {
                    $runAllowed = true;
                    break;
                }
            }
        }

        if (!$runAllowed) {

            return 'Execution not allowed at this time';
        }

        /**
         * Make sure to process only those queue items with where action and code not matching any running queue items per website
         */
        $queues = $this->queueCollectionFactory->create();
        $queues->getSelect()->reset();
        $queues->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->where('q1.code = ?', $type)
            ->where('q1.status = ?', QueueModel::STATUS_PENDING);

        if (($type == OrderModel::CODE && $this->config->getIsHoldNewOrdersExport())) {
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
        $this->saveLastEntityExportTime($type);

        return sprintf('Processed %d %s item(s) in the queue.', $count, $type);
    }

    /**
     * @param string $type
     *
     * @return int
     */
    public function saveLastEntityExportTime($type)
    {
        $flagCode = $this->getExportLastRunFlagCode($type);
        if (!$flagCode) {

            return false;
        }

        $time = time();
        $this->flagFactory->create()
            ->setMconnectFlagCode($flagCode)
            ->loadSelf()
            ->setLastUpdate(date('Y-d-m H:i:s', $time))
            ->save();

        return $time;
    }

    /**
     * @return bool|int
     */
    public function getLastEntityExportTime($type)
    {
        $flagCode = $this->getExportLastRunFlagCode($type);
        if (!$flagCode) {

            return false;
        }

        $flag = $this->flagFactory->create()
            ->setMconnectFlagCode($flagCode)
            ->loadSelf();

        $time = $flag->hasData() ? $flag->getLastUpdate() : false;
        if (!$time) {

            return false;
        }

        return strtotime($time);
    }

    public function getExportLastRunFlagCode($type)
    {
        if (!in_array($type, [OrderModel::CODE, CustomerModel::CODE])) {

            return false;
        }

        $flagCode = self::FLAG_CUSTOMERS_EXPORT_LAST_RUN_TIME;
        if ($type == OrderModel::CODE) {
            $flagCode = self::FLAG_ORDERS_EXPORT_LAST_RUN_TIME;
        }

        return $flagCode;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function autoResyncErrorOrders()
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }
        $maxRetryAmount = $config->get('order/auto_retry_attempts');
        $retryDelay = $config->get('order/auto_retry_delay');
        $ordersAmount = $config->get('order/auto_retry_batch_size');
        $ordersPeriod = (int)$config->get('order/auto_retry_period');
        if ($ordersPeriod) {
            $orderPeriodToTime = date("y-m-d", strtotime("-$ordersPeriod day"));
        } else {
            $orderPeriodToTime = date("y-m-d", strtotime("-1 month"));
        }

        $items = $this->queueCollectionFactory->create();
        $items = $items->addFieldToFilter('status', ['eq' => QueueModel::STATUS_ERROR])
            ->addFieldToFilter('code', ['eq' => OrderModel::CODE])
            ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
            ->addFieldToFilter('retry_count', ['lt' => $maxRetryAmount])
            ->addFieldToFilter('created_at', ['from' => $orderPeriodToTime]);
        if ($ordersAmount) {
            $items->getSelect()->limit($ordersAmount);
        }

        $count = $items->getSize();
        if (!$count) {

            return 'No items in queue to retry.';
        }

        $prepareOrdersToEmail = [];
        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($items as $item) {
            if (!$item->getEntityId()) {

                continue;
            }
            $order = $this->salesOrderFactory->create()->load($item->getEntityId());
            if ($order->getStatus() == 'kount_review') {

                continue;
            }

            $status = $item->process();
            if ($item->getRetryCount() == ($maxRetryAmount - 1) && $status == QueueModel::STATUS_ERROR) {
                $entityId = '#' . $order->getIncrementId();
                $prepareOrdersToEmail[] = $entityId;
            }
            $item->getResource()->incrementRetryCount($item->getId());
            sleep($retryDelay);
        }

        if (count($prepareOrdersToEmail) > 0) {
            $this->mConnectMailer->sendRetryOrderErrorEmail([
                'error_title' => 'List of orders with error status after ' . $maxRetryAmount . ' attempts to retry',
                'orders'      => implode(", ", $prepareOrdersToEmail),
                'attempts'    => $maxRetryAmount
            ]);
        }

        return sprintf('Resynced %d errored previously order(s) in the queue.', $count);
    }
}