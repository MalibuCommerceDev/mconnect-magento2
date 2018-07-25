<?php

namespace MalibuCommerce\MConnect\Plugin;

use Magento\Sales\Model\OrderInterface;

/**
 * Plugin for Magento\Sales\Model\Order.
 *
 * @see Order
 */
class OrderPlugin
{
    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queue;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * OrderPlugin constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\QueueFactory $queue
     * @param \Psr\Log\LoggerInterface                    $logger
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->queue = $queue;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $result
     *
     * @return mixed
     */
    public function afterCancel(\Magento\Sales\Model\Order $order, $result)
    {
        if ($order->isCanceled()) {
            $this->removePendingExportOrder($order);
        }

        return $result;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $result
     *
     * @return mixed
     */
    public function afterHold(\Magento\Sales\Model\Order $order, $result)
    {
        if ($order->getState() === \Magento\Sales\Model\Order::STATE_HOLDED) {
            $this->removePendingExportOrder($order);
        }

        return $result;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $result
     *
     * @return mixed
     */
    public function afterUnhold(\Magento\Sales\Model\Order $order, $result)
    {
        if ($order->canHold() && !$this->queue->create()->wasTheItemEverSuccessfullyExported($order->getId())) {
            try {
                $this->queue->create()->add('order', 'export', $order->getId());
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }
        }

        return $result;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    protected function removePendingExportOrder(\Magento\Sales\Model\Order $order)
    {
        try {
            if ($order && $order->getId()) {
                $this->queue->create()->removePendingItemsByEntityId(
                    $order->getId(),
                    sprintf('Order "%s" (ID: %s) export is canceled because its status is: %s', $order->getIncrementId(), $order->getId(), $order->getStatus())
                );
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}