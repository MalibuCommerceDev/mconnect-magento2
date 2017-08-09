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
        return $this->queueImportItem('customer');
    }

    public function queueProductImport()
    {
        return $this->queueImportItem('product');
    }

    public function queueShipmentImport()
    {
        return $this->queueImportItem('shipment');
    }

    public function queueInvoiceImport()
    {
        return $this->queueImportItem('invoice');
    }

    public function queuePriceRuleImport()
    {
        return $this->queueImportItem('price_rule');
    }

    protected function queueImportItem($code)
    {
        $config = $this->config;
        if (!$config->getFlag('general/enabled')) {
            return 'M-Connect is disabled).';
        }
        $queue = $this->queue->create()->add(
            $code,
            'import'
        );
        if ($queue->getId()) {
            return 'Item added to queue.';
        }

        return 'Failed to add item to queue.';
    }
}
