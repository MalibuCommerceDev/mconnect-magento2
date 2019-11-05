<?php

namespace MalibuCommerce\MConnect\Model\Queue\Inventory;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;


/**
 * Save source product relations during inventory sync from NAV to Magento
 *
 * Inspired by Magento\InventoryCatalogAdminUi\Observer\ProcessSourceItemsObserver
 */
class SourceItemsProcessor
{
    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    protected $isSourceItemManagementAllowedForProductType;

    /**
     * @var \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor
     */
    protected $sourceItemsProcessor;

    /**
     * @var IsSingleSourceModeInterface
     */
    private $isSingleSourceMode;

    /**
     * @var DefaultSourceProviderInterface
     */
    protected $defaultSourceProvider;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var SourceItemRepositoryInterface
     */
    protected $sourceItemRepository;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * SourceItemsProcessor constructor.
     *
     * @param IsSourceItemManagementAllowedForProductTypeInterface           $isSourceItemManagementAllowedForProductType
     * @param \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor $sourceItemsProcessor
     * @param IsSingleSourceModeInterface                                    $isSingleSourceMode
     * @param DefaultSourceProviderInterface                                 $defaultSourceProvider
     * @param SearchCriteriaBuilderFactory                                   $searchCriteriaBuilderFactory
     * @param SourceItemRepositoryInterface                                  $sourceItemRepository
     * @param ProductRepositoryInterface                                     $productRepository
     */
    public function __construct(
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        \Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor  $sourceItemsProcessor,
        IsSingleSourceModeInterface $isSingleSourceMode,
        DefaultSourceProviderInterface $defaultSourceProvider,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SourceItemRepositoryInterface $sourceItemRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->sourceItemsProcessor = $sourceItemsProcessor;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sourceItemRepository = $sourceItemRepository;
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
