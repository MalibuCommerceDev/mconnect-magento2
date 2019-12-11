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
        $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();

        if ($order->canHold() && !$this->queue->create()->wasTheItemEverSuccessfullyExported($order->getId(), 'order')) {
            try {
                $customerGroupId = $order->getCustomerGroupId();
                if (in_array($customerGroupId, $this->config->getOrderExportDisallowedCustomerGroups($websiteId))) {

                    return $result;
                }
                $this->queue->create()->add(
                    \MalibuCommerce\MConnect\Model\Queue\Order::CODE,
                    \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                    $websiteId,
                    0,
                    $order->getId(),
                    $order->getIncrementId()
                );
            } catch (\Throwable $e) {
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
                $this->queue->create()->removePendingItems(
                    $order->getId(),
                    \MalibuCommerce\MConnect\Model\Queue\Order::CODE,
                    sprintf('Order "%s" (ID: %s) export is canceled because its status is: %s', $order->getIncrementId(), $order->getId(), $order->getStatus())
                );
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }
    }
}