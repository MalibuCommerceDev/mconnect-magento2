<?php

namespace MalibuCommerce\MConnect\Model\Cron;

use \MalibuCommerce\Mconnect\Model\Queue as QueueModel;
use \MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;
use \MalibuCommerce\MConnect\Model\Queue\Customer as CustomerModel;

class Queue
{
    const FLAG_EXPORT_ENTITY_MASK = 'malibucommerce_mconnect_%ss_export_last_run_time';

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

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /** @var \MalibuCommerce\MConnect\Model\FlagFactory */
    protected $flagFactory;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \MalibuCommerce\MConnect\Helper\Mail $mConnectMailer,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \MalibuCommerce\MConnect\Model\FlagFactory $flagFactory
    ) {
        $this->config = $config;
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->date = $date;
        $this->mConnectMailer = $mConnectMailer;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->customerFactory = $customerFactory;
        $this->flagFactory = $flagFactory;
    }

    /**
     * @param bool $forceSyncNow
     *
     * @return string
     * @throws \Exception
     */
    public function process($forceSyncNow = false)
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        /**
         * Make sure to process only those queue items where action and code not matching any running items per website
         */
        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $queueItems */
        $queueItems = $this->queueCollectionFactory->create();
        $queueItems->getSelect()->reset();
        $queueItems->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->joinLeft(
                ['q2' => 'malibucommerce_mconnect_queue'],
                $queueItems->getConnection()->quoteInto(
                    'q1.code = q2.code AND q1.action = q2.action AND q1.website_id = q2.website_id AND q2.status = ?',
                    QueueModel::STATUS_RUNNING
                ),
                []
            )
            ->where('q1.status = ?', QueueModel::STATUS_PENDING)
            ->where('q2.id IS NULL');

        if (!$forceSyncNow || ($forceSyncNow instanceof \Magento\Cron\Model\Schedule)) {
            $queueItems->getSelect()->where('q1.scheduled_at <= ?', $this->date->gmtDate());
        }

        $items = 0;
        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($queueItems as $item) {
            if (($item->getCode() == CustomerModel::CODE) && ($item->getAction() == QueueModel::ACTION_EXPORT)) {
                continue;
            }
            if (($item->getCode() == OrderModel::CODE) && ($item->getAction() == QueueModel::ACTION_EXPORT)) {
                continue;
            }

            $items++;
            $item->process();
        }

        if ($items == 0) {
            return 'No items in the queue for processing.';
        }

        return sprintf('Processed %d item(s) in queue.', $items);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function processExportsOnly()
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        /**
         * Make sure to process only those queue items where action and code not matching any running items per website
         */
        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $queueItems */
        $queueItems = $this->queueCollectionFactory->create();
        $queueItems->getSelect()->reset();
        $queueItems->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->joinLeft(
                ['q2' => 'malibucommerce_mconnect_queue'],
                $queueItems->getConnection()->quoteInto(
                    'q1.code = q2.code AND q1.action = q2.action AND q1.website_id = q2.website_id AND q2.status = ?',
                    QueueModel::STATUS_RUNNING
                ),
                []
            )
            ->where('q1.action = ?', QueueModel::ACTION_EXPORT)
            ->where('q1.status = ?', QueueModel::STATUS_PENDING)
            ->where('q2.id IS NULL');

        $count = $queueItems->getSize();
        if (!$count) {
            return 'No items in the queue for processing.';
        }

        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($queueItems as $item) {
            $item->process();
        }

        return sprintf('Processed %d item(s) in queue.', $count);
    }

    /**
     * @return string
     * @throws \Exception
     */
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

        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $queueItems */
        $queueItems = $this->queueCollectionFactory->create();
        $queueItems = $queueItems->olderThanDays($value, $this->date);
        $count = $queueItems->getSize();
        if (!$count) {

            return 'No items in queue to remove.';
        }

        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($queueItems as $item) {
            $item->delete();
        }

        return sprintf('Removed %d item(s) from the queue.', $count);
    }

    /**
     * @return string
     * @throws \Exception
     */
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



        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $queueItems */
        $queueItems = $this->queueCollectionFactory->create();
        $queueItems = $queueItems->olderThanMinutes($value, $this->date)
            ->addFieldToFilter('status', ['eq' => QueueModel::STATUS_RUNNING]);
        $count = $queueItems->getSize();
        if (!$count) {
            return 'No items in queue to mark with error status.';
        }

        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($queueItems as $item) {
            $message = sprintf("Marked as staled after %s minutes\n\n", $value);
            $message .= $item->getMessage();
            $message = mb_strimwidth(
                $message,
                0,
                \MalibuCommerce\MConnect\Helper\Data::QUEUE_ITEM_MAX_MESSAGE_SIZE,
                '...'
            );

            $item->setStatus(QueueModel::STATUS_ERROR)
                ->setMessage($message)
                ->save();
        }

        return sprintf('Marked %d item(s) in queue.', $count);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function exportCustomers()
    {
        return $this->exportEntitiesByType(CustomerModel::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function exportOrders()
    {
        return $this->exportEntitiesByType(OrderModel::CODE);
    }

    /**
     * @param string $type
     *
     * @return string
     * @throws \Exception
     */
    public function exportEntitiesByType($type)
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }

        if (!in_array($type, [OrderModel::CODE, CustomerModel::CODE])) {
            return 'Export for ' . $type. 's is not supported.';
        }

        if (!$config->canExportEntityType($type, $this->getLastEntityExportTime($type))) {

            return 'Export for ' . $type. 's is not allowed at this time.';
        }

        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $queueItems */
        $queueItems = $this->queueCollectionFactory->create();
        $queueItems->getSelect()->reset();
        $queueItems->getSelect()
            ->from(['q1' => 'malibucommerce_mconnect_queue'], '*')
            ->where('q1.code = ?', $type)
            ->where('q1.status = ?', QueueModel::STATUS_PENDING);

        if ($type == OrderModel::CODE && $this->config->getIsHoldNewOrdersExport()) {
            $queueItems->getSelect()->where('q1.scheduled_at <= ?', $this->date->gmtDate());
        }

        $count = $queueItems->getSize();
        if (!$count) {

            return 'No items found in the queue for processing.';
        }

        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($queueItems as $item) {
            $item->process();
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

    /**
     * @param string $type
     *
     * @return bool|string
     */
    public function getExportLastRunFlagCode($type)
    {
        if (!in_array($type, [OrderModel::CODE, CustomerModel::CODE])) {

            return false;
        }

        return sprintf(self::FLAG_EXPORT_ENTITY_MASK, $type);
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
        $batchSize = $config->get('order/auto_retry_batch_size');
        $erroredItemsPeriod = (int)$config->get('order/auto_retry_period');
        if ($erroredItemsPeriod) {
            $createdAtFrom = date("y-m-d", strtotime("-$erroredItemsPeriod day"));
        } else {
            $createdAtFrom = date("y-m-d", strtotime("-1 month"));
        }

        $items = $this->queueCollectionFactory->create();
        $items = $items->addFieldToFilter('status', ['eq' => QueueModel::STATUS_ERROR])
            ->addFieldToFilter('code', ['eq' => OrderModel::CODE])
            ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
            ->addFieldToFilter('retry_count', ['lt' => $maxRetryAmount])
            ->addFieldToFilter('created_at', ['from' => $createdAtFrom]);
        if ($batchSize) {
            $items->getSelect()->limit($batchSize);
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

            $success = $this->queueCollectionFactory->create()
                ->addFieldToFilter('status', ['eq' => QueueModel::STATUS_SUCCESS])
                ->addFieldToFilter('code', ['eq' => OrderModel::CODE])
                ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
                ->addFieldToFilter('entity_id', ['eq' => $item->getEntityId()])
                ->addFieldToFilter('created_at', ['from' => $item->getCreatedAt()])
                ->getSize();
            if ($success) {
                // queue item was successfully processed elsewhere, prevent this queue from retrying again
                $item->setRetryCount($maxRetryAmount)->save();
                continue;
            }

            $order = $this->salesOrderFactory->create()->load($item->getEntityId());
            if ($order->getStatus() == 'kount_review') {

                continue;
            }

            $status = $item->process();
            if ($item->getRetryCount() == ($maxRetryAmount - 1) && $status == QueueModel::STATUS_ERROR) {
                $prepareOrdersToEmail[] = $order->getIncrementId();
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

    /**
     * @return string
     * @throws \Exception
     */
    public function autoResyncErroredCustomers()
    {
        $config = $this->config;
        if (!$config->isModuleEnabled()) {

            return 'Module is disabled.';
        }
        $maxRetryAmount = $config->get('customer/auto_retry_attempts');
        $retryDelay = $config->get('customer/auto_retry_delay');
        $batchSize = $config->get('customer/auto_retry_batch_size');
        $erroredItemsPeriod = (int)$config->get('customer/auto_retry_period');
        if ($erroredItemsPeriod) {
            $createdAtFrom = date("y-m-d", strtotime("-$erroredItemsPeriod day"));
        } else {
            $createdAtFrom = date("y-m-d", strtotime("-1 month"));
        }

        $items = $this->queueCollectionFactory->create();
        $items = $items->addFieldToFilter('status', ['eq' => QueueModel::STATUS_ERROR])
            ->addFieldToFilter('code', ['eq' => CustomerModel::CODE])
            ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
            ->addFieldToFilter('retry_count', ['lt' => $maxRetryAmount])
            ->addFieldToFilter('created_at', ['from' => $createdAtFrom]);
        if ($batchSize) {
            $items->getSelect()->limit($batchSize);
        }

        $count = $items->getSize();
        if (!$count) {

            return 'No items in queue to retry.';
        }

        $erroredCustomersForEmailReport = [];
        /** @var \MalibuCommerce\MConnect\Model\Queue $item */
        foreach ($items as $item) {
            if (!$item->getEntityId()) {

                continue;
            }

            $success = $this->queueCollectionFactory->create()
                ->addFieldToFilter('status', ['eq' => QueueModel::STATUS_SUCCESS])
                ->addFieldToFilter('code', ['eq' => CustomerModel::CODE])
                ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
                ->addFieldToFilter('entity_id', ['eq' => $item->getEntityId()])
                ->addFieldToFilter('created_at', ['from' => $item->getCreatedAt()])
                ->getSize();
            if ($success) {
                // queue item was successfully processed elsewhere, prevent this queue from retrying again
                $item->setRetryCount($maxRetryAmount)->save();
                continue;
            }

            $customerDataModel = $this->customerFactory->create()->load($item->getEntityId());
            if (!$customerDataModel->getId()) {

                continue;
            }

            $status = $item->process();
            if ($item->getRetryCount() == ($maxRetryAmount - 1) && $status == QueueModel::STATUS_ERROR) {
                $erroredCustomersForEmailReport[] = $customerDataModel->getId();
            }
            $item->getResource()->incrementRetryCount($item->getId());
            sleep($retryDelay);
        }

        if (count($erroredCustomersForEmailReport) > 0) {
            $this->mConnectMailer->sendRetryCustomerErrorEmail([
                'error_title' => 'List of customers with error status after ' . $maxRetryAmount . ' attempts to retry',
                'customer'    => implode(", ", $erroredCustomersForEmailReport),
                'attempts'    => $maxRetryAmount
            ]);
        }

        return sprintf('Resynced %d errored previously customer(s) in the queue.', $count);
    }
}
