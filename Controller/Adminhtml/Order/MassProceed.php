<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Order;

use \Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use \Magento\Backend\App\Action\Context;
use \Magento\Ui\Component\MassAction\Filter;
use MalibuCommerce\MConnect\Model\Queue as QueueModel;

class MassProceed extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

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
     * MassProceed constructor.
     *
     * @param Context                                                              $context
     * @param Filter                                                               $filter
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory           $orderCollectionFactory
     * @param \MalibuCommerce\MConnect\Model\QueueFactory                          $queue
     * @param \MalibuCommerce\MConnect\Model\Config                                $config
     * @param \Psr\Log\LoggerInterface                                             $logger
     * @param \Magento\Store\Model\StoreManagerInterface                           $storeManager
     * @param \MalibuCommerce\MConnect\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory
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

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $processedSyncs = 0;
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $notAllowedtoSyncOrderIds = $syncErrorOrderIds = [];
        foreach ($collection->getItems() as $order) {
            try {
                $scheduledAt = null;
                $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

                $customerGroupId = (string)$order->getCustomerGroupId();
                if (in_array($customerGroupId, $this->config->getOrderExportDisallowedCustomerGroups($websiteId))) {
                    $notAllowedtoSyncOrderIds[] = $order->getId();
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
                    $queue->process();
                    $processedSyncs++;
                }

            } catch (\Throwable $e) {
                $syncErrorOrderIds[] = $order->getId();
                $this->logger->critical($e);
            }
        }
        if ($processedSyncs) {
            $this->messageManager->addSuccessMessage(__('Processed %1 order(s)', $processedSyncs));
        }
        if (!empty($notAllowedtoSyncOrderIds)) {
            $this->messageManager->addWarningMessage(
                __('Order IDs %1 were not synced because their Customer Group is dissallowed for exports', implode(', ', $notAllowedtoSyncOrderIds))
            );
        }
        if (!empty($syncErrorOrderIds)) {
            $this->messageManager->addErrorMessage(
                __('Order IDs %1 were not synced because of some sync errors. See system.log', implode(', ', $syncErrorOrderIds))
            );
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('sales/*/');
        return $resultRedirect;
    }
}
