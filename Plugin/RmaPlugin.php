<?php

namespace MalibuCommerce\MConnect\Plugin;

/**
 * Used to override product price on PLP
 */
class RmaPlugin
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
     * Plugin to apply MConnect Price Rules for Product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param float $originalFinalPrice
     *
     * @return float|null
     */
    public function afterSaveRma(\Magento\Rma\Model\Rma $rma, $result)
    {
        if (!$this->config->isModuleEnabled()) {

            return $this;
        }

        if ($result) {
            $this->queueNewItem(
                \MalibuCommerce\MConnect\Model\Queue\Rma::CODE,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                $result
            );
        }

        return $result;
    }

    /**
     * @param $code
     * @param $action
     * @param \Magento\Rma\Model\Rma $order
     *
     * @return bool|\MalibuCommerce\MConnect\Model\Queue
     */
    protected function queueNewItem($code, $action, $rma)
    {
        try {
            $scheduledAt = null;
            $websiteId = $this->storeManager->getStore($rma->getStoreId())->getWebsiteId();

            return $this->queue->create()->add($code, $action, $websiteId, 0, $rma->getId(), [], $scheduledAt);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}