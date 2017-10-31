<?php
namespace MalibuCommerce\MConnect\Observer;

class SalesOrderAfterPlaceObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->queue = $queue;
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
            return $this->queue->create()->add($code, $action, $id, $details);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return false;
    }
}
