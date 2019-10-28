<?php
namespace MalibuCommerce\MConnect\Observer;

class SalesEventCreditmemoSaveObserver implements \Magento\Framework\Event\ObserverInterface
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

        $creditmemo = $observer->getEvent()->getCreditmemo();
        if ($creditmemo && !$creditmemo->getSkipMconnect()) {
            $this->queueNewItem(
                \MalibuCommerce\MConnect\Model\Queue\Creditmemo::CODE,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                $creditmemo
            );
        }

        return $this;
    }

    /**
     * @param $code
     * @param $action
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     *
     * @return bool|\Magento\Framework\DataObject|\MalibuCommerce\MConnect\Model\Queue
     */
    protected function queueNewItem($code, $action, $creditmemo)
    {
        try {
            $websiteId = $this->storeManager->getStore($creditmemo->getStoreId())->getWebsiteId();

            return $this->queue->create()->add($code, $action, $websiteId, 0, $creditmemo->getId());
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
