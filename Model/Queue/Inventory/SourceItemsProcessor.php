<?php

namespace MalibuCommerce\MConnect\Model\Queue\Inventory;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Inventory\Model\ResourceModel\Source\Collection as SourceCollection;
use Magento\Inventory\Model\ResourceModel\Source\CollectionFactory as SourceCollectionFactory;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryCatalogApi\Model\SourceItemsProcessorInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;

/**
 * Save source product relations during inventory sync from NAV to Magento
 *
 * Inspired by Magento\InventoryCatalogAdminUi\Observer\ProcessSourceItemsObserver
 */
class SourceItemsProcessor
{
    /** @var IsSourceItemManagementAllowedForProductTypeInterface */
    protected $isSourceItemManagementAllowedForProductType;

    /** @var SourceItemsProcessorInterface */
    protected $sourceItemsProcessor;

    /** @var DefaultSourceProviderInterface */
    protected $defaultSourceProvider;

    /**
     * @var IsSingleSourceModeInterface
     */
    protected $isSingleSourceMode;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var SourceCollectionFactory
     */
    protected $sourceCollectionFactory;

    /** @var  SourceItemRepositoryInterface */
    protected $sourceItemRepository;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        ObjectManagerInterface $objectManager,
        ProductRepositoryInterface $productRepository,
        ProductMetadataInterface $productMetadata
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->productRepository = $productRepository;
        $this->productMetadata = $productMetadata;
        $this->objectManager = $objectManager;
    }

    /**
     * Process inventory source items during inventory sync from NAV to Magento
     *
     * @param ProductInterface $product
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     * @param int $defaultSourceQty
     * @param null|bool $isInStock
     *
     * @return array|string
     *
     * @throws InputException
     */
    public function process($product, \SimpleXMLElement $data, int $websiteId, int $defaultSourceQty, $isInStock = null)
    {
        $this->isSourceItemManagementAllowedForProductType = $this->objectManager->create(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->sourceItemsProcessor = $this->objectManager->create(
            SourceItemsProcessorInterface::class
        );
        $this->defaultSourceProvider = $this->objectManager->create(
            DefaultSourceProviderInterface::class
        );
        $this->sourceItemRepository = $this->objectManager->create(
            SourceItemRepositoryInterface::class
        );

        $sourceItemQty = [];
        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId()) === false) {
            return sprintf(
                '%s: skipped - is not allowed management of source items for %s product type' . PHP_EOL,
                $product->getSku(),
                $product->getTypeId()
            );
        }

        $sourceCodes = $this->getInventorySourceCodes($websiteId);
        if (empty($sourceCodes)) {
            return sprintf(
                '%s: skipped - inventory sources not found for website id #%s' . PHP_EOL,
                $product->getSku(),
                $websiteId
            );
        }
        if (in_array($this->defaultSourceProvider->getCode(), $sourceCodes)) {
            $sourceItemQty[$this->defaultSourceProvider->getCode()] = $defaultSourceQty;
        }
        foreach ($sourceCodes as $sourceCode) {
            $sourceCodeNode = strtoupper($sourceCode);
            if (isset($data->$sourceCodeNode)) {
                $sourceItemQty[$sourceCode] = (int)$data->$sourceCodeNode;
            }
            $sourceCodeNode = strtolower($sourceCode);
            if (isset($data->$sourceCodeNode)) {
                $sourceItemQty[$sourceCode] = (int)$data->$sourceCodeNode;
            }
        }
        $sourceItems = $this->getSourceItems($product->getSku());
        $newSourceItemsCode = array_diff(
            array_keys($sourceItemQty),
            array_column($sourceItems, SourceItemInterface::SOURCE_CODE)
        );
        foreach ($newSourceItemsCode as $newSourceItemCode) {
            $sourceItems[] = [
                SourceItemInterface::SKU         => $product->getSku(),
                SourceItemInterface::SOURCE_CODE => $newSourceItemCode,
            ];
        }
        foreach ($sourceItems as $key => $sourceItem) {
            if (!isset($sourceItemQty[$sourceItem[SourceItemInterface::SOURCE_CODE]])) {
                continue;
            }
            $qty = (int)$sourceItemQty[$sourceItem[SourceItemInterface::SOURCE_CODE]];
            $sourceItem[SourceItemInterface::QUANTITY] = $qty;
            $sourceItem[SourceItemInterface::STATUS] = $isInStock == null
                ? $sourceItem[SourceItemInterface::STATUS]
                : (int)(bool)$qty;
            $sourceItems[$key] = $sourceItem;
        }
        $this->sourceItemsProcessor->execute($product->getSku(), $sourceItems);

        return $sourceItemQty;
    }

    /**
     * Get Source Items Data without Default Source by SKU
     *
     * @param string $sku
     *
     * @return array
     */
    protected function getSourceItems($sku)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(SourceItemInterface::SKU, $sku);

        $searchCriteria = $searchCriteriaBuilder->create();

        $this->sourceItemRepository = $this->objectManager->create(
            SourceItemRepositoryInterface::class
        );
        $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();

        $sourceItemData = [];
        if ($sourceItems) {
            foreach ($sourceItems as $sourceItem) {
                $sourceItemData[] = [
                    SourceItemInterface::SKU => $sourceItem->getSku(),
                    SourceItemInterface::SOURCE_CODE => $sourceItem->getSourceCode(),
                    SourceItemInterface::QUANTITY => $sourceItem->getQuantity(),
                    SourceItemInterface::STATUS => $sourceItem->getStatus()
                ];
            }
        }
        return $sourceItemData;
    }

    /**
     * Check support and enable MSI
     *
     * @return bool
     */
    public function isSupportMSI()
    {
        if (version_compare($this->productMetadata->getVersion(), '2.3.0', '<')
            || !interface_exists(IsSingleSourceModeInterface::class)
        ) {
            return false;
        }
        if (empty($this->isSingleSourceMode)) {
            $this->isSingleSourceMode = $this->objectManager->create(IsSingleSourceModeInterface::class);
        }

        return !$this->isSingleSourceMode->execute();
    }

    /**
     * Get inventory source codes except default
     *
     * @param int $websiteId
     *
     * @return array
     */
    protected function getInventorySourceCodes($websiteId)
    {
        if (empty($this->sourceCollectionFactory)) {
            $this->sourceCollectionFactory = $this->objectManager->create(
                SourceCollectionFactory::class
            );
        }
        /** @var SourceCollection $sourceCollection */
        $sourceCollection = $this->sourceCollectionFactory->create();
        if (!empty($websiteId)) {
            $sourceCollection
                ->join(
                    ['issl' => 'inventory_source_stock_link'],
                    'main_table.source_code = issl.source_code',
                    ''
                )
                ->join(
                    ['issc' => 'inventory_stock_sales_channel'],
                    'issl.stock_id = issc.stock_id',
                    ''
                )
                ->join(
                    ['sw' => 'store_website'],
                    sprintf('issc.code = sw.code && issc.type = "%s"', SalesChannelInterface::TYPE_WEBSITE),
                    ''
                )
                ->addFieldToFilter('website_id', $websiteId);
        }
        $sourceCollection
            ->addFieldToFilter(SourceInterface::ENABLED, true);

        return $sourceCollection->getColumnValues(SourceInterface::SOURCE_CODE);
    }
}
