<?php

namespace MalibuCommerce\MConnect\Model\Queue\Inventory;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

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

    private $isSingleSourceMode;

    protected $defaultSourceProvider;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    protected $sourceItemRepository;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * SourceItemsProcessor constructor.
     *
     * @param SearchCriteriaBuilderFactory                                   $searchCriteriaBuilderFactory
     * @param ProductRepositoryInterface                                     $productRepository
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        ProductRepositoryInterface $productRepository
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->productRepository = $productRepository;
    }

    /**
     * Process inventory source items during inventory sync from NAV to Magento
     *
     * @param ProductInterface $product
     * @param int $qty
     * @param bool $isInStock
     *
     * @return ProductInterface|void
     * @throws NoSuchEntityException
     */
    public function process($product, $qty, $isInStock = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->isSourceItemManagementAllowedForProductType = $objectManager->create(
            \Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->sourceItemsProcessor = $objectManager->create(
            \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor::class
        );
        $this->defaultSourceProvider = $objectManager->create(
            \Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface::class
        );
        $this->sourceItemRepository = $objectManager->create(
            \Magento\InventoryApi\Api\SourceItemRepositoryInterface::class
        );
        $this->isSingleSourceMode = $objectManager->create(
            \Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface::class
        );

        if ($this->isSourceItemManagementAllowedForProductType->execute($product->getTypeId()) === false) {
            return;
        }

        // @todo allow updating not only default source data
        // $singleSourceMode = $this->isSingleSourceMode->execute();

        $sourceItems = $this->getSourceItems($product->getSku());
        foreach ($sourceItems as &$sourceItem) {
            if ($sourceItem->getSourceCode() == $this->defaultSourceProvider->getCode()) {
                $sourceItem[SourceItemInterface::QUANTITY] = (int)$qty;
                $sourceItem[SourceItemInterface::STATUS] = $isInStock === null
                    ? $sourceItem->getStatus()
                    : (bool)$isInStock;
                break;
            }
        }
        $this->sourceItemsProcessor->process($product->getSku(), $sourceItems);

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
