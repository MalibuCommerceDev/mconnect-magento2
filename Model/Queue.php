<?php

namespace MalibuCommerce\MConnect\Model;

/**
 * @method Queue setCode(string $code)
 * @method Queue setAction(string $action)
 * @method Queue setWebsiteId(int $websiteId)
 * @method Queue setNavPageNum(int $navPageNumber)
 * @method Queue setDetails(string $details)
 * @method Queue setScheduledAt(string $date)
 * @method Queue setCreatedAt(string $date)
 * @method Queue setStartedAt(string $date)
 * @method Queue setStatus(string $status)
 * @method string getCode()
 * @method string getAction()
 * @method int getWebsiteId()
 * @method int getNavPageNum()
 * @method string getDetails()
 * @method string getMessage()
 * @method Queue setMessage(string $message)
 */
class Queue extends \Magento\Framework\Model\AbstractModel
{
    const CODE = 'entity';
    const NAV_XML_NODE_ITEM_NAME = 'entity';

    const ACTION_IMPORT = 'import';
    const ACTION_EXPORT = 'export';

    const STATUS_PENDING  = 'pending';
    const STATUS_RUNNING  = 'running';
    const STATUS_SUCCESS  = 'success';
    const STATUS_WARNING  = 'warning';
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
     * @var int
     */
    protected $affectedEntitiesCount = 0;

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

    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queueFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\QueueFactory $queueFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->queueFactory = $queueFactory;

        parent::__construct($context, $registry);
    }

    public function _construct()
    {
        $this->_init(\MalibuCommerce\MConnect\Model\ResourceModel\Queue::class);
    }

    /**
     * Add new queue item
     *
     * @param string        $code
     * @param string        $action
     * @param int           $websiteId
     * @param int           $navPageNumber
     * @param null          $id
     * @param null          $increment_id
     * @param array         $details
     * @param null          $scheduledAt
     * @param bool          $retrieveIfExists
     *
     * @return $this|\Magento\Framework\DataObject
     * @throws \Exception
     */
    public function add(
        $code,
        $action,
        $websiteId = 0,
        $navPageNumber = 0,
        $id = null,
        $increment_id = null,
        $details = [],
        $scheduledAt = null,
        $retrieveIfExists = false
    ) {
        if (!$this->getConfig()->isModuleEnabled()) {

            return $this;
        }

        if ($action == self::ACTION_IMPORT
            && !(bool)$this->getConfig()->getWebsiteData($code . '/import_enabled', $websiteId)
        ) {
            return $this;
        }

        if ($action == self::ACTION_EXPORT
            && !(bool)$this->getConfig()->getWebsiteData($code . '/export_enabled', $websiteId)
        ) {
            return $this;
        }

        $this->unsetData();
        $id = $id ? $id : null;
        $scheduledAt = $scheduledAt ?? date('Y-m-d H:i:s');
        if (empty($details)) {
            $details = null;
        }
        $details = is_array($details) ? json_encode($details) : $details;
        $navPageNumber = (int)$navPageNumber;

        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue\Collection $collection */
        $collection = $this->getCollection();
        $item = $collection->findMatchingPending($code, $action, $websiteId, $navPageNumber, $id, $details);

        if (!$item->getSize()) {
            $this->setCode($code)
                ->setAction($action)
                ->setWebsiteId($websiteId)
                ->setNavPageNum($navPageNumber)
                ->setEntityId($id)
                ->setEntityIncrementId($increment_id)
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

    /**
     * Requeue current item
     *
     * @return $this|\Magento\Framework\DataObject
     * @throws \Exception
     */
    public function reQueue()
    {
        return $this->add(
            $this->getCode(),
            $this->getAction(),
            $this->getWebsiteId(),
            $this->getNavPageNum(),
            $this->getEntityId(),
            $this->getTitle(),
            $this->getDetails()
        );
    }

    /**
     * Process current queue item
     *
     * @return bool|string
     * @throws \Exception
     */
    public function process()
    {
        if (!$this->getConfig()->isModuleEnabled()) {

            return false;
        }

        $code = $this->getCode();
        $action = $this->getAction();
        $websiteId = $this->getWebsiteId();

        if ($action == self::ACTION_EXPORT
            && !(bool)$this->getConfig()->getWebsiteData($code . '/export_enabled', $websiteId)
        ) {

            return false;
        }

        $this->setStatus(self::STATUS_RUNNING)
            ->setStartedAt(date('Y-m-d H:i:s'))
            ->save();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  \MalibuCommerce\MConnect\Model\Queue $model */
        $model = $objectManager->create('MalibuCommerce\MConnect\Model\Queue\\' . ucwords(str_replace('_', '', $code)));

        $prefix = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $action))));
        $method = $prefix . 'Action';

        if (!method_exists($model, $method)) {
            $this->endProcess(
                self::STATUS_ERROR,
                sprintf('M-Connect error: The action "%s" is not recognized and cannot be processed.  You may need to update the M-Connect module or clear the Magento cache.', $action)
            );

            return self::STATUS_ERROR;
        }

        $this->registry->unregister('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM');
        $this->registry->register('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM', $this);

        $affectedEntitiesCount = 0;
        try {
            if (($code == \MalibuCommerce\MConnect\Model\Queue\Customer::CODE
                 || $code == \MalibuCommerce\MConnect\Model\Queue\Order::CODE
                 || $code == \MalibuCommerce\MConnect\Model\Queue\Creditmemo::CODE) && ($action == self::ACTION_EXPORT)
            ) {
                $model->{$method}($this->getEntityId());
            } else {
                $model->setDetails($this->getDetails());
                $model->{$method}($websiteId, $this->getNavPageNum());
            }

            if ($model->captureEntityId) {
                $this->setEntityId($model->getEntityId());
            }

            $resultedStatus = $model->getMagentoErrorsDetected() ? self::STATUS_WARNING : self::STATUS_SUCCESS;
            $messages = $model->getMessages();
            $affectedEntitiesCount = $model->getAffectedEntitiesCount();
        } catch (\Throwable $e) {
            $this->_logger->critical($e);
            $messages = 'Processing interrupted!' . "\n" . 'Error: ' . $e->getMessage()
                        . ($model->getMessages() ? "\n\nProcessing Messages: " . $model->getMessages() : '');
            $resultedStatus = self::STATUS_ERROR;
        }

        $this->endProcess($resultedStatus, $messages, $affectedEntitiesCount);
        $this->registry->unregister('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM');

        return $resultedStatus;
    }

    protected function endProcess($status, $message = null, $affectedEntitiesCount = 0)
    {
        $message = mb_strimwidth(
            $message . "\n\n" . $this->getMessage(),
            0,
            \MalibuCommerce\MConnect\Helper\Data::QUEUE_ITEM_MAX_MESSAGE_SIZE,
            '...'
        );

        $this->setMessage($message)
            ->setAffectedEntitiesCnt($affectedEntitiesCount)
            ->setFinishedAt(date('Y-m-d H:i:s'))
            ->setStatus($status)
            ->save();

        return $this;
    }

    /**
     * @param Navision\AbstractModel $navExporter
     * @param Queue\ImportableEntity|\MalibuCommerce\MConnect\Model\Queue $magentoImporter
     * @param                        $websiteId
     * @param int                    $navPageNumber
     *
     * @return $this|bool|\Magento\Framework\DataObject
     * @throws \Exception
     */
    public function processMagentoImport(
        Navision\AbstractModel $navExporter,
        Queue\ImportableEntity $magentoImporter,
        $websiteId,
        $navPageNumber = 0
    ) {
        $processedPages = $affectedEntitiesCount = 0;
        $detectedErrors = $lastSync = false;
        $maxPagesPerRun = $this->config->get('queue/max_pages_per_execution');
        $lastUpdated = $this->getLastSyncTime($this->getImportLastSyncFlagName($websiteId));
        do {
            $result = $navExporter->export($navPageNumber, $lastUpdated, $websiteId);
            foreach ($result->{$this->getNavXmlNodeName()} as $data) {
                try {
                    $importResult = $magentoImporter->importEntity($data, $websiteId);
                    if ($importResult) {
                        $affectedEntitiesCount++;
                    }
                } catch (\Throwable $e) {
                    $detectedErrors = true;
                    $magentoImporter->addMessage($e->getMessage());
                }
                $magentoImporter->addMessage('');
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
            $processedPages++;
            $navPageNumber++;
            if ($processedPages >= $maxPagesPerRun && $this->hasRecords($result)) {
                if ($affectedEntitiesCount > 0) {
                    $magentoImporter->addMessage('Successfully processed ' . $affectedEntitiesCount . ' NAV record(s).');
                } else {
                    $magentoImporter->addMessage('Nothing to import.');
                }

                return $this->queueFactory->create()->add(
                    $magentoImporter->getQueueCode(),
                    self::ACTION_IMPORT,
                    $websiteId,
                    $navPageNumber
                );
            }
        } while ($this->hasRecords($result));

        if (!$detectedErrors
            || $this->config->getWebsiteData($magentoImporter->getQueueCode() . '/ignore_magento_errors', $websiteId)
        ) {
            $this->setLastSyncTime($this->getImportLastSyncFlagName($websiteId), $lastSync);
        }

        $magentoImporter->setMagentoErrorsDetected($detectedErrors);

        if ($affectedEntitiesCount > 0) {
            $magentoImporter->addAffectedEntitiesCount($affectedEntitiesCount);
            $magentoImporter->addMessage('Successfully processed ' . $affectedEntitiesCount . ' NAV record(s).');
        } else {
            $magentoImporter->addMessage('Nothing to import.');
        }

        return true;
    }

    public function getLastSyncTime($code)
    {
        $flag = $this->queueFlagFactory->create()->setQueueFlagCode($code)->loadSelf();
        $time = $flag->hasData() ? $flag->getLastUpdate() : '';
        if (!$time) {
            return false;
        }

        return date('Y-m-d\TH:i:s', strtotime($time));
    }

    public function setLastSyncTime($code, $time)
    {
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

    public function getAffectedEntitiesCount()
    {
        return $this->affectedEntitiesCount;
    }

    public function addAffectedEntitiesCount($count)
    {
        return $this->affectedEntitiesCount = $count;
    }

    public function addMessage($message)
    {
        return $this->messages .= $message . PHP_EOL;
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

    public function removePendingItems($entityId, $code, $message = null)
    {
        return $this->getResource()->removePendingItems($entityId, $code, $message);
    }

    public function wasTheItemEverSuccessfullyExported($entityId, $code)
    {
        return $this->getResource()->wasTheItemEverSuccessfullyExported($entityId, $code);
    }

    public function deleteQueueItemById($itemId)
    {
        return $this->getResource()->deleteQueueItemById($itemId);
    }

    public function getQueueCode()
    {
        return static::CODE;
    }

    public function getNavXmlNodeName()
    {
        return static::NAV_XML_NODE_ITEM_NAME;
    }

    public function getImportLastSyncFlagName($websiteId = 0)
    {
        $flagName = sprintf('malibucommerce_mconnect_%s_sync_time', $this->getQueueCode());
        if (!empty($websiteId)) {
            $flagName .= '_' . (int)$websiteId;
        }

        return $flagName;
    }
}
