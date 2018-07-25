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

    public function __construct(
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->queue = $queue;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Address after save event handler
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
            $this->queue('order', 'export', $order->getId());
        }
    }

    protected function queue($code, $action, $id = null, $details = array())
    {
        try {
            $scheduledAt = null;
            if ($this->config->getIsHoldNewOrdersExport() || $this->config->shouldNewOrdersBeForcefullyHolden()) {
                $delayInMinutes =  $this->config->getHoldNewOrdersDelay();
                $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . (int)$delayInMinutes . ' minutes'));
            }

            return $this->queue->create()->add($code, $action, $id, $details, $scheduledAt);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
