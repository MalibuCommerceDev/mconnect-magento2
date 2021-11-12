<?php

namespace MalibuCommerce\MConnect\Model\Queue\Inventory;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;

/**
 * Save source product relations during inventory sync from NAV to Magento
 *
 * Inspired by Magento\InventoryCatalogAdminUi\Observer\ProcessSourceItemsObserver
 */
class SourceItemsProcessor
{

    protected $isSourceItemManagementAllowedForProductType;

    /**
     * @var \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor
     */
    protected $sourceItemsProcessor;

    protected $defaultSourceProvider;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    protected $sourceItemRepository;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected $sourceItemFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SourceItemInterfaceFactory $sourceItemInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SourceItemInterfaceFactory  $sourceItemFactory,
        ProductRepositoryInterface $productRepository
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->productRepository = $productRepository;
    }

    /**
     * Process inventory source items during inventory sync from NAV to Magento
     *
     * @param ProductInterface $product
     * @param array $sourceItemQty
     * @param bool $isInStock
     *
     * @return ProductInterface|void
     * @throws NoSuchEntityException
     */
    public function process($product, $sourceItemQty, $isInStock = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->isSourceItemManagementAllowedForProductType = $objectManager->create(
            \Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->sourceItemsProcessor = $objectManager->create(
            \Magento\InventoryCatalogApi\Model\SourceItemsProcessorInterface::class
        );
        $this->defaultSourceProvider = $objectManager->create(
            \Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface::class
        );
        $this->sourceItemRepository = $objectManager->create(
            \Magento\InventoryApi\Api\SourceItemRepositoryInterface::class
        );

        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId()) === false) {
            return;
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

        return $this;
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
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(SourceItemInterface::SKU, $sku);

        $searchCriteria = $searchCriteriaBuilder->create();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->sourceItemRepository = $objectManager->create(
            \Magento\InventoryApi\Api\SourceItemRepositoryInterface::class
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
}
