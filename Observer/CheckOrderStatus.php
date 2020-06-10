<?php
namespace MalibuCommerce\MConnect\Observer;

use MalibuCommerce\MConnect\Model\Queue as QueueModel;
use MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;

class CheckOrderStatus implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queue;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection
     */
    protected $queueCollectionFactory;

    public function __construct(
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MalibuCommerce\MConnect\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory
    ) {
        $this->queue = $queue;
        $this->config = $config;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->queueCollectionFactory = $queueCollectionFactory;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();
        $isOrderExportFilteringBeforeQueue = $this->config->isOrderExportStatusFilteringBeforeQueueEnabled($websiteId);

        if (!$this->config->isModuleEnabled() || !$isOrderExportFilteringBeforeQueue) {

            return $this;
        }

        $originalOrderStatus = $order->getOrigData('status');
        $orderStatus = $order->getStatus();
        $allowedStatusesToSync = $this->config->getOrderStatuesAllowedForSync($websiteId);
        $items = $this->queueCollectionFactory->create();
        $items = $items->addFieldToFilter('entity_id', ['eq' => $order->getEntityId()])
            ->addFieldToFilter('code', ['eq' => OrderModel::CODE])
            ->addFieldToFilter('action', ['eq' => QueueModel::ACTION_EXPORT])
            ->addFieldToFilter('status', ['in' => [QueueModel::STATUS_PENDING, QueueModel::STATUS_RUNNING, QueueModel::STATUS_SUCCESS]]);
        $orderDoesntExistInQueue = !(bool)$items->getSize();
        if ($order && !$order->getSkipMconnect()
            && $originalOrderStatus
            && ($originalOrderStatus != $orderStatus)
            && in_array($order->getStatus(), $allowedStatusesToSync)
            && $orderDoesntExistInQueue
        ) {
            $this->queueNewItem(
                \MalibuCommerce\MConnect\Model\Queue\Order::CODE,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                $order
            );
        }

        return $this;
    }

    /**
     * @param $code
     * @param $action
     * @param \Magento\Sales\Model\Order $order
     *
     * @return bool|\MalibuCommerce\MConnect\Model\Queue
     */
    protected function queueNewItem($code, $action, $order)
    {
        try {
            $scheduledAt = null;
            $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

            $customerGroupId = $order->getCustomerGroupId();
            if (in_array((string)$customerGroupId, $this->config->getOrderExportDisallowedCustomerGroups($websiteId))) {

                return false;
            }

            if ($this->config->getIsHoldNewOrdersExport($websiteId) || $this->config->shouldNewOrdersBeForcefullyHeld()) {
                $delayInMinutes =  $this->config->getHoldNewOrdersDelay($websiteId);
                $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . (int)$delayInMinutes . ' minutes'));
            }

            return $this->queue->create()->add($code, $action, $websiteId, 0, $order->getId(), $order->getIncrementId(), [], $scheduledAt);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
