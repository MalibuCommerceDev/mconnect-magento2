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
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();
        if ($order && !$order->getSkipMconnect()) {
            $this->queue('order', 'export', $order);
        }
    }

    /**
     * @param $code
     * @param $action
     * @param \Magento\Sales\Model\Order $order
     *
     * @return bool|\MalibuCommerce\MConnect\Model\Queue
     */
    protected function queue($code, $action, $order)
    {
        try {
            $scheduledAt = null;
            $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();
            if ($this->config->getIsHoldNewOrdersExport($websiteId) || $this->config->shouldNewOrdersBeForcefullyHolden()) {
                $delayInMinutes =  $this->config->getHoldNewOrdersDelay($websiteId);
                $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . (int)$delayInMinutes . ' minutes'));
            }

            return $this->queue->create()->add($code, $action, $websiteId, $order->getId(), [], $scheduledAt);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
