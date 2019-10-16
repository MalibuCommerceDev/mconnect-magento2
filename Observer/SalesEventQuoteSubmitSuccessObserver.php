<?php
namespace MalibuCommerce\MConnect\Observer;

class SalesEventQuoteSubmitSuccessObserver implements \Magento\Framework\Event\ObserverInterface
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

    public function __construct(
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->queue = $queue;
        $this->config = $config;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Add order to export queue
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isModuleEnabled()) {

            return $this;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        if ($order && !$order->getSkipMconnect()) {
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

            return $this->queue->create()->add($code, $action, $websiteId, 0, $order->getId(), [], $scheduledAt);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
