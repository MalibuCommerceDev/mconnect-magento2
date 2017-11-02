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
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $object = $observer->getCustomer();
        if (!$object->getSkipMconnect()) {
            $this->_queue('customer', 'export', $object->getId());
        }
    }

    protected function _queue($code, $action, $id = null, $details = array())
    {
        try {
            return $this->queue->create()->add($code, $action, $id, $details);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return false;
    }
}
