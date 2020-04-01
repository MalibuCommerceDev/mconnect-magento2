<?php

namespace MalibuCommerce\MConnect\Model;

use MalibuCommerce\MConnect\Model\Queue as QueueModel;
use MalibuCommerce\MConnect\Model\Queue\Customer as CustomerModel;

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

    public function queueCustomerImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Customer::CODE);
    }

    public function queueProductImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Product::CODE);
    }

    public function queueInventoryImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Inventory::CODE);
    }

    public function queueInvoiceImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Invoice::CODE);
    }

    public function queueShipmentImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Shipment::CODE);
    }

    public function queuePriceRuleImport()
    {
        return $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Pricerule::CODE);
    }

    public function queueRmaImport()
    {
        if ($this->moduleManager->isEnabled('Magento_Rma')) {
            $this->queueImportItem(\MalibuCommerce\MConnect\Model\Queue\Rma::CODE);
        }
    }

    protected function queueImportItem($code)
    {
        if (!$this->config->isModuleEnabled()) {

            return 'M-Connect is disabled.';
        }

        $activeWebsites = $this->getMultiCompanyActiveWebsites();

        foreach ($activeWebsites as $websiteId) {
            if (!(bool)$this->config->getWebsiteData($code . '/import_enabled', $websiteId)) {
                return sprintf('Import functionality is disabled for %s at Website ID "%s"', $code, $websiteId) . PHP_EOL;
                continue;
            }

            $queue = $this->queue->create()->add(
                $code,
                \MalibuCommerce\MConnect\Model\Queue::ACTION_IMPORT,
                $websiteId,
                0,
                null,
                null,
                [],
                null,
                true
            );

            if ($this->isScheduleProcessAllow($code)) {
                $queue->process();
                $this->saveLastEntityImportTime($code);

                return sprintf('The "%s" item added/exists in the queue and proceed for Website ID "%s"', $code, $websiteId) . PHP_EOL;

            } else {
                if ($queue->getId()) {
                    return sprintf('The "%s" item added/exists in the queue for Website ID "%s"', $code, $websiteId) . PHP_EOL;
                } else {
                    return sprintf('Failed to add new %s item added to queue for Website ID "%s"', $code, $websiteId) . PHP_EOL;
                }
            }

        }

        return true;
    }

    public function isScheduleProcessAllow($code)
    {
        $currentTime = time();
        $config = $this->config;

        if (!$config->isScheduledEntityImportEnabled($code)) {

            return false;
        }
        $lastProcessingTime = $this->getLastEntityImportTime($code);
        if ($lastProcessingTime && $config->getScheduledEntityImportDelayTime($code) > 0
            && ($currentTime - $lastProcessingTime) < $config->getScheduledEntityImportDelayTime($code)
        ) {

            return false;
        }

        $lastProcessingTime = !$lastProcessingTime ? strtotime('12:00 AM') : $lastProcessingTime;
        $runTimes = $config->getScheduledEntityImportRunTimes($code);
        if (!$runTimes) {
            $runAllowed = true;
        } else {
            $runAllowed = false;
            foreach ($runTimes as $strTime) {
                $scheduledTime = strtotime($strTime);
                if ($currentTime >= $scheduledTime && $scheduledTime > $lastProcessingTime) {
                    $runAllowed = true;
                    break;
                }
            }
        }

        if (!$runAllowed) {

            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function saveLastEntityImportTime($code)
    {
        $time = time();
        $this->flagFactory->create()
            ->setMconnectFlagCode('malibucommerce_mconnect_' . $code . '_import_last_run_time')
            ->loadSelf()
            ->setLastUpdate(date('Y-d-m H:i:s', $time))
            ->save();

        return $time;
    }

    /**
     * @return bool|int
     */
    public function getLastEntityImportTime($code)
    {
        $flag = $this->flagFactory->create()
            ->setMconnectFlagCode('malibucommerce_mconnect_' . $code . '_import_last_run_time')
            ->loadSelf();

        $time = $flag->hasData() ? $flag->getLastUpdate() : false;
        if (!$time) {

            return false;
        }

        return strtotime($time);
    }


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
