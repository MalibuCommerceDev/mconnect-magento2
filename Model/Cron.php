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

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /** @var \MalibuCommerce\MConnect\Model\FlagFactory  */
    protected $flagFactory;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Magento\Framework\Module\Manager $moduleManager,
        \MalibuCommerce\MConnect\Model\FlagFactory $flagFactory
    ) {
        $this->config = $config;
        $this->queue = $queue;
        $this->moduleManager = $moduleManager;
        $this->flagFactory = $flagFactory;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queueCustomerImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Customer::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queueProductImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Product::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queueInventoryImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Inventory::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queueInvoiceImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Invoice::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queueShipmentImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Shipment::CODE);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function queuePriceRuleImport()
    {
        return $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Pricerule::CODE);
    }

    /**
     * @throws \Exception
     */
    public function queueRmaImport()
    {
        if ($this->moduleManager->isEnabled('Magento_Rma')) {
            $this->importEntitiesByType(\MalibuCommerce\MConnect\Model\Queue\Rma::CODE);
        }
    }

    /**
     * @param string $entityType
     *
     * @return string
     * @throws \Exception
     */
    protected function importEntitiesByType($entityType)
    {
        if (!$this->config->isModuleEnabled()) {

            return 'M-Connect is disabled.';
        }

        $activeWebsites = $this->getMultiCompanyActiveWebsites();
        $canProcess = $this->config->canImportEntityType($entityType, $this->getLastEntityImportTime($entityType));
        $messages = '';

        foreach ($activeWebsites as $websiteId) {
            if (!(bool)$this->config->getWebsiteData($entityType . '/import_enabled', $websiteId)) {
                $messages .= sprintf('Import functionality is disabled for "%s" at Website ID "%s"', $entityType, $websiteId) . PHP_EOL;
                continue;
            }

            $queueItem = $this->queue->create()->add(
                $entityType,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_IMPORT,
                $websiteId,
                0,
                null,
                null,
                [],
                null,
                true
            );

            if ($canProcess) {
                $queueItem->process();
                $this->saveLastEntityImportTime($entityType);

                $messages .= sprintf('The "%s" import queue items added/exist in the queue and proceed for Website ID "%s"', $entityType, $websiteId) . PHP_EOL;

            } else {
                if ($queueItem->getId()) {
                    $messages .= sprintf('The "%s" import queue items added/exist in the queue for Website ID "%s"', $entityType, $websiteId) . PHP_EOL;
                } else {
                    $messages .= sprintf('Failed to add new "%s" import queue items to queue for Website ID "%s"', $entityType, $websiteId) . PHP_EOL;
                }
            }

        }

        return $messages;
    }

    /**
     * @param string $entityType
     *
     * @return int
     */
    public function saveLastEntityImportTime($entityType)
    {
        $time = time();
        $this->flagFactory->create()
            ->setMconnectFlagCode('malibucommerce_mconnect_' . $entityType . '_import_last_run_time')
            ->loadSelf()
            ->setLastUpdate(date('Y-d-m H:i:s', $time))
            ->save();

        return $time;
    }

    /**
     * @param string $entityType
     *
     * @return bool|false|int
     */
    public function getLastEntityImportTime($entityType)
    {
        $flag = $this->flagFactory->create()
            ->setMconnectFlagCode('malibucommerce_mconnect_' . $entityType . '_import_last_run_time')
            ->loadSelf();

        $time = $flag->hasData() ? $flag->getLastUpdate() : false;
        if (!$time) {

            return false;
        }

        return strtotime($time);
    }

    /**
     * @return array
     */
    public function getMultiCompanyActiveWebsites()
    {
        $connection = $this->queue->create()->getResource()->getConnection();
        $select = $connection->select()
            ->from('core_config_data', ['scope', 'scope_id'])
            ->where('path = \'malibucommerce_mconnect/nav_connection/url\'');
        $scopes = $connection->fetchPairs($select);

        $websiteIds = [0];
        if (!empty($scopes)) {
            foreach ($scopes as $scope => $scopeId) {
                if (!in_array($scope, ['default', 'websites'])) {
                    continue;
                }

                $websiteIds[] = $scopeId;
            }
        }

        return array_unique($websiteIds);
    }
}
