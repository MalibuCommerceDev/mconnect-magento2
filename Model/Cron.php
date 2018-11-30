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

    public function queueInventoryImport()
    {
        return $this->queueImportItem('inventory');
    }

    public function queueInvoiceImport()
    {
        return $this->queueImportItem('invoice');
    }

    public function queueShipmentImport()
    {
        return $this->queueImportItem('shipment');
    }

    public function queuePriceRuleImport()
    {
        return $this->queueImportItem('price_rule');
    }

    protected function queueImportItem($code)
    {
        if (!$this->config->isModuleEnabled()) {

            return 'M-Connect is disabled.';
        }

        $activeWebsites = $this->getMultiCompanyActiveWebsites();
        $messages = '';

        foreach ($activeWebsites as $websiteId) {
            if (!(bool)$this->config->getWebsiteData($code . '/import_enabled', $websiteId)) {
                $messages .= sprintf('Import functionality is disabled for %s at Website ID %s', $code, $websiteId);
                continue;
            }

            $queue = $this->queue->create()->add(
                $code,
                'import',
                $websiteId
            );
            if ($queue->getId()) {
                $messages .= sprintf('New %s item added to queue for Website ID %s', $code, $websiteId);
            } else {
                $messages .= sprintf('Failed to add new %s item added to queue for Website ID %s', $code, $websiteId);
            }
        }

        return $messages;
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
