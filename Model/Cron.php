<?php
namespace MalibuCommerce\MConnect\Model;


class Cron
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectQueue = $mConnectQueue;
    }
    public function queueCustomerImport()
    {
        $config = $this->mConnectConfig;
        if (!$config->getFlag('general/enabled')) {
            return 'M-Connect is disabled).';
        }
        $queue = $this->mConnectQueue->add(
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
        $config = $this->mConnectConfig;
        if (!$config->getFlag('general/enabled')) {
            return 'M-Connect is disabled).';
        }
        $queue = $this->mConnectQueue->add(
            'product',
            'import'
        );
        if ($queue->getId()) {
            return 'Item added to queue.';
        }
        return 'Failed to add item to queue.';
    }
}
