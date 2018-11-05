<?php

namespace MalibuCommerce\MConnect\Model;

class Queue extends \Magento\Framework\Model\AbstractModel
{
    const STATUS_PENDING  = 'pending';
    const STATUS_RUNNING  = 'running';
    const STATUS_SUCCESS  = 'success';
    const STATUS_ERROR    = 'error';
    const STATUS_CANCELED = 'canceled';

    /**
     * @var string
     */
    protected $_eventPrefix = 'malibucommerce_mconnect_queue';

    /**
     * @var string
     */
    protected $_eventObject = 'queue';

    /**
     * @var bool
     */
    protected $captureEntityId = false;

    /**
     * @var string
     */
    protected $messages = '';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queueFlagFactory = $queueFlagFactory;

        parent::__construct($context, $registry);
    }

    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Queue');
    }

    public function add($code, $action, $websiteId = 0, $id = null, $details = array(), $scheduledAt = null, $retrieveIfExists = false)
    {
        if (!$this->getConfig()->isModuleEnabled()) {

            return $this;
        }

        if ($action == 'import' && !(bool)$this->getConfig()->getWebsiteData($code . '/import_enabled', $websiteId)) {

            return $this;
        }

        $this->unsetData();
        $id = $id ? $id : null;
        $scheduledAt = $scheduledAt ?? date('Y-m-d H:i:s');
        $details = is_array($details) ? (count($details) ? json_encode($details) : null) : $details;
        $item = $this->getCollection()->findMatchingPending($code, $action, $websiteId, $id, $details);

        if (!$item->getSize()) {
            $this->setCode($code)
                ->setAction($action)
                ->setWebsiteId($websiteId)
                ->setEntityId($id)
                ->setDetails($details)
                ->setScheduledAt($scheduledAt)
                ->setCreatedAt(date('Y-m-d H:i:s'))
                ->setStatus(self::STATUS_PENDING)
                ->save();
        } elseif ($retrieveIfExists) {
            return $item->getFirstItem();
        }

        return $this;
    }

    public function reQueue()
    {
        return $this->add(
            $this->getCode(),
            $this->getAction(),
            $this->getWebsiteId(),
            $this->getEntityId(),
            $this->getDetails()
        );
    }

    public function process()
    {
        if (!$this->getConfig()->isModuleEnabled()) {

            return $this;
        }

        $this->setStatus(self::STATUS_RUNNING)
            ->setStartedAt(date('Y-m-d H:i:s'))
            ->save();

        $code = $this->getCode();
        $websiteId = $this->getWebsiteId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  \MalibuCommerce\MConnect\Model\Queue $model */
        $model = $objectManager->create('MalibuCommerce\MConnect\Model\Queue\\' . ucwords(str_replace('_', '', $code)));

        $action = $this->getAction();
        $prefix = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $action))));
        $method = $prefix . 'Action';

        if (!method_exists($model, $method)) {
            $this->endProcess(
                self::STATUS_ERROR,
                sprintf('M-Connect error: The action "%s" is not recognized and cannot be processed.  You may need to update the M-Connect module or clear the Magento cache.', $action)
            );

            return $this;
        }
        $this->registry->register('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM_ID', $this->getId());

        try {
            if (($code == 'customer' || $code == 'order') && ($action == 'export')) {
                $model->{$method}($this->getEntityId());
            } else {
                $model->setDetails($this->getDetails());
                $model->{$method}($websiteId);
            }

            if ($model->captureEntityId) {
                $this->setEntityId($model->getEntityId());
            }
            $this->endProcess(self::STATUS_SUCCESS, $model->getMessages());
        } catch (\Throwable $e) {
            $this->_logger->critical($e);
            $this->endProcess(self::STATUS_ERROR, $e->getMessage());
        }

        $this->registry->unregister('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM_ID', $this->getId());

        return $this;
    }

    protected function endProcess($status, $message = null)
    {
        $this->setMessage($message)
            ->setFinishedAt(date('Y-m-d H:i:s'))
            ->setStatus($status)
            ->save();

        return $this;
    }

    public function getLastSyncTime($code, $websiteId = null)
    {
        if (!empty($websiteId)) {
            $code .= '_' . $websiteId;
        }
        $flag = $this->queueFlagFactory->create()->setQueueFlagCode($code)->loadSelf();
        $time = $flag->hasData() ? $flag->getLastUpdate() : '';
        if (!$time) {
            return false;
        }

        return date('Y-m-d\TH:i:s', strtotime($time));
    }

    public function setLastSyncTime($code, $time, $websiteId = null)
    {
        if (!empty($websiteId)) {
            $code .= '_' . $websiteId;
        }
        $this->queueFlagFactory->create()->setQueueFlagCode($code)->loadSelf()
            ->setLastUpdate($time)
            ->save();

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function hasRecords($result)
    {
        if (isset($result->status->end_of_records) && (string)$result->status->end_of_records === 'true') {
            return false;
        }
        if ((int)$result->status->record_count <= 0) {
            return false;
        }

        return true;
    }

    public function removePendingItemsByEntityId($entityId, $message = null)
    {
        return $this->getResource()->removePendingItemsByEntityId($entityId, $message);
    }

    public function wasTheItemEverSuccessfullyExported($entityId)
    {
        return $this->getResource()->wasTheItemEverSuccessfullyExported($entityId);
    }
}
