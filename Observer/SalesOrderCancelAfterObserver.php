<?php

namespace MalibuCommerce\MConnect\Observer;

class SalesOrderCancelAfterObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queue;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getOrder();
            if ($order && $order->getId()) {
                $this->queue->create()->removePendingItemsByEntityId(
                    $order->getId(),
                    'Order was canceled, no need to export to NAV'
                );
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
