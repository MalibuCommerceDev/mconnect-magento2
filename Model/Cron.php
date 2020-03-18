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

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->config = $config;
        $this->queue = $queue;
        $this->moduleManager = $moduleManager;
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
            if ($queue->getId()) {
                return sprintf('The "%s" item added/exists in the queue for Website ID "%s"', $code, $websiteId) . PHP_EOL;
            } else {
                return sprintf('Failed to add new %s item added to queue for Website ID "%s"', $code, $websiteId) . PHP_EOL;
            }
        }

        return true;
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
