<?php

namespace MalibuCommerce\MConnect\Observer;

class AfterCustomerSaveObserver implements \Magento\Framework\Event\ObserverInterface
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
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Address after save event handler
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isModuleEnabled()) {

            return $this;
        }

        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getCustomer();
        if (!$customer->getSkipMconnect()) {
            $websiteId = $customer->getWebsiteId();
            $this->queueNewItem(
                \MalibuCommerce\MConnect\Model\Queue\Customer::CODE,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT,
                $websiteId,
                $customer->getId()
            );
        }

        return $this;
    }

    protected function queueNewItem($code, $action, $websiteId = 0, $id)
    {
        try {
            return $this->queue->create()->add($code, $action, $websiteId, 0, $id);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
