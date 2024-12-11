<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Order;

use \Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use \Magento\Backend\App\Action\Context;
use \Magento\Ui\Component\MassAction\Filter;
use MalibuCommerce\MConnect\Model\Queue as QueueModel;

class MassSynced extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $collectionFactory;

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

    /**
     * @param \Magento\Backend\App\Action\Context                        $context
     * @param \Magento\Ui\Component\MassAction\Filter                    $filter
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \MalibuCommerce\MConnect\Model\QueueFactory                $queue
     * @param \MalibuCommerce\MConnect\Model\Config                     vibмшимшvi $config
     * @param \Psr\Log\LoggerInterface                      $logger
     * @param \Magento\Store\Model\StoreManagerInterface    $storeManager
     * @param \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection
     */
    public function __construct(
        Context $context,
        Filter $filter,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MalibuCommerce\MConnect\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $orderCollectionFactory;
        $this->queue = $queue;
        $this->config = $config;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->queueCollectionFactory = $queueCollectionFactory;
    }

    public function execute()
    {
        $countMassQueue = 0;
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        foreach ($collection->getItems() as $order) {

            try {
                $scheduledAt = null;
                $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

                $customerGroupId = $order->getCustomerGroupId();
                if (in_array((string)$customerGroupId, $this->config->getOrderExportDisallowedCustomerGroups($websiteId))) {
                    continue;
                }

                if ($this->config->getIsHoldNewOrdersExport($websiteId) || $this->config->shouldNewOrdersBeForcefullyHeld()) {
                    $delayInMinutes =  $this->config->getHoldNewOrdersDelay($websiteId);
                    $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . (int)$delayInMinutes . ' minutes'));
                }

                $this->queue->create()->add(
                    \MalibuCommerce\MConnect\Model\Queue\Order::CODE,
                    \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                    $websiteId,
                    0,
                    $order->getId(),
                    $order->getIncrementId(),
                    [],
                    $scheduledAt
                );
                $queues = $this->queueCollectionFactory->create();
                $queues = $queues->addFieldToFilter('entity_id', $order->getId())->setOrder('id', 'DESC');
                $queue = $queues->getFirstItem();
                if ($queue) {
                    $queue->setStatus(QueueModel::STATUS_SUCCESS)
                        ->setFinishedAt(date('Y-m-d H:i:s'))
                        ->save();
                    $countMassQueue++;
                }

            } catch (\Throwable $e) {
                $this->logger->critical($e);
            }
        }

        $countNonAddedOrder = $collection->count() - $countMassQueue;

        if ($countNonAddedOrder && $countMassQueue) {
            $this->messageManager->addErrorMessage(__('%1 order(s) cannot be marked as synced.', $countNonAddedOrder));
        } elseif ($countNonAddedOrder) {
            $this->messageManager->addErrorMessage(__('You cannot mark the order(s) synced.'));
        }

        if ($countMassQueue) {
            $this->messageManager->addSuccessMessage(__('We marked as synced %1 order(s).', $countMassQueue));
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('sales/*/');
        return $resultRedirect;
    }
}
