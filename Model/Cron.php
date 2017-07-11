<?php

namespace MalibuCommerce\MConnect\Model;

class Cron
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue
    ) {
        $this->config = $config;
        $this->queue = $queue;
    }

    public function queueCustomerImport()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'M-Connect is disabled).';
        }
        $queue = $this->queue->create()->add(
            'customer',
            'import'
        );
        if ($queue->getId()) {
            return 'Item added to queue.';
        }

        return 'Failed to add item to queue.';
    }

    public function queueProductImport()
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'M-Connect is disabled).';
        }
        $queue = $this->queue->create()->add(
            'product',
            'import'
        );
        if ($queue->getId()) {
            return 'Item added to queue.';
        }

        return 'Failed to add item to queue.';
    }
}
