<?php

namespace MalibuCommerce\MConnect\Model\PriceRuleImport;

use Magento\Framework\App\CacheInterface;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\CollectionFactory;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\Collection;

class Cron
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $priceRuleImportCollectionFactory;

    /**
     * @var ImportProcessor
     */
    private ImportProcessor $importProcessor;

    /**
     * @var CacheInterface
     */
    private CacheInterface $appCache;

    /**
     * @param CollectionFactory $priceRuleImportCollectionFactory
     * @param ImportProcessor $importProcessor
     * @param CacheInterface $appCache
     */
    public function __construct(
        CollectionFactory $priceRuleImportCollectionFactory,
        ImportProcessor $importProcessor,
        CacheInterface $appCache
    ) {
        $this->priceRuleImportCollectionFactory = $priceRuleImportCollectionFactory;
        $this->importProcessor = $importProcessor;
        $this->appCache = $appCache;
    }

    /**
     * @return void
     */
    public function execute()
    {
        /** @var Collection $priceRuleImportCollection */
        $priceRuleImportCollection = $this->priceRuleImportCollectionFactory->create();
        $priceRuleImportCollection->addFieldToFilter('status', ['in' => [PriceRuleImport::STATUS_PENDING]]);

        $processedCount = 0;
        foreach ($priceRuleImportCollection as $priceRuleImport) {
            $processedCount += $this->importProcessor->process($priceRuleImport);
        }

        if ($processedCount) {
            $this->appCache->clean();
        }
    }
}
