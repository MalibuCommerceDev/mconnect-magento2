<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Registry;
use MalibuCommerce\MConnect\Model\QueueFactory;

class Pricerule extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE                           = 'price_rule';
    const NAV_XML_NODE_ITEM_NAME         = 'sales_price';
    const DEFAULT_CUSTOMER_CURRENCY_CODE = 'USD';

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Pricerule
     */
    protected $navPriceRule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queueFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Pricerule $navPriceRule,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Pricerule $rule,
        FlagFactory $queueFlagFactory,
        QueueFactory $queueFactory,
        Registry $registry
    ) {
        $this->navPriceRule = $navPriceRule;
        $this->config = $config;
        $this->rule = $rule;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->queueFactory = $queueFactory;
        $this->registry = $registry;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPriceRule, $this, $websiteId, $navPageNumber);
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
        \MalibuCommerce\MConnect\Model\Navision\AbstractModel $navExporter,
        ImportableEntity $magentoImporter,
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

            /**
             * Added support for Price Rules removal within the same Price Rules NAV export logic:
             */
            foreach ($result->{$this->getNavXmlNodeName() . '_del'} as $data) {
                try {
                    $deleteResult = $magentoImporter->deleteEntity($data, $websiteId);
                    if ($deleteResult) {
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

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int               $websiteId
     */
    public function importPriceRule($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $currencyCode = null;
        if (isset($data->currency_code)) {
            $currencyCode = (string)$data->currency_code;
            if (empty($currencyCode)) {
                $currencyCode = $this->getDefaultCurrencyCode();
            }
        }

        $modelData = [
            'nav_id'               => (int)$data->unique_id,
            'website_id'           => (int)$websiteId,
            'sku'                  => (string)$data->nav_item_id,
            'currency_code'        => $currencyCode,
            'navision_customer_id' => (string)$data->nav_customer_id,
            'qty_min'              => (int)$data->min_quantity,
            'price'                => (float)$data->unit_price,
            'customer_price_group' => (string)$data->cust_price_group,
            'date_start'           => ((string)$data->start_date) ? date('Y:m:d H:i:s', strtotime((string)$data->start_date)) : null,
            'date_end'             => ((string)$data->end_date) ? date('Y:m:d H:i:s', strtotime((string)$data->end_date)) : null,
        ];

        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection $collection */
        $collection = $this->rule->getCollection()
            ->addFilter('nav_id', (int)$data->unique_id)
            ->addFilter('website_id', (int)$websiteId)
            ->setPageSize(1)
            ->setCurPage(1);

        /** @var \MalibuCommerce\MConnect\Model\Pricerule $model */
        $model = $collection->getFirstItem();
        $isUpdate = $model && $model->getId();
        $model->addData($modelData);
        try {
            $model->save();
            $this->messages .= sprintf(
                'Price Rule Nav ID %s: %s',
                $model->getNavId(),
                ($isUpdate ? 'UPDATED' : 'CREATED')
            );
        } catch (\Throwable $e) {
            $this->messages .= sprintf(
                'Price Rule Nav ID %s (SKU %s): ERROR - %s',
                $model->getNavId(),
                $model->getSku(),
                $e->getMessage()
            );
        }

        return true;
    }

    public function deleteEntity(\SimpleXMLElement $data, $websiteId)
    {
        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection $collection */
        $collection = $this->rule->getCollection()
            ->addFilter('nav_id', (int)$data->unique_id)
            ->addFilter('website_id', (int)$websiteId)
            ->setPageSize(1)
            ->setCurPage(1);

        /** @var \MalibuCommerce\MConnect\Model\Pricerule $model */
        $model = $collection->getFirstItem();
        if (!$model || !$model->getId()) {

            return false;
        }

        try {
            $model->delete();
            $this->messages .= sprintf(
                'Price Rule Nav ID %s: DELETED',
                $model->getNavId()
            );
        } catch (\Throwable $e) {
            $this->messages .= sprintf(
                'Price Rule Nav ID %s (SKU %s): DELETE ERROR - %s',
                $model->getNavId(),
                $model->getSku(),
                $e->getMessage()
            );
        }

        return true;
    }

    /**
     * @return string
     */
    public function getDefaultCurrencyCode()
    {
        return static::DEFAULT_CUSTOMER_CURRENCY_CODE;
    }
}
