<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Inventory\Model\ResourceModel\Source\Collection as SourceCollection;
use Magento\Inventory\Model\ResourceModel\Source\CollectionFactory as SourceCollectionFactory;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Model\Navision\Inventory as NavisionInventory;
use MalibuCommerce\MConnect\Model\Queue;
use MalibuCommerce\MConnect\Model\Queue\Inventory\SourceItemsProcessor;

class Inventory extends Queue implements ImportableEntity
{
    const CODE = 'inventory';
    const NAV_XML_NODE_ITEM_NAME = 'item_inventory';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StockConfigurationInterface
     */
    protected $stockConfiguration;

    /**
     * @var DefaultSourceProviderInterface
     */
    protected $defaultSourceProvider;

    /**
     * @var IsSingleSourceModeInterface
     */
    protected $isSingleSourceMode;

    /**
     * @var NavisionInventory
     */
    protected $navisionInventory;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var SourceCollectionFactory
     */
    protected $sourceCollectionFactory;

    /**
     * @var SourceItemsProcessor
     */
    protected $sourceItemsProcessor;

    /**
     * @param Config $config
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param FlagFactory $queueFlagFactory
     * @param NavisionInventory $navisionInventory
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param ProductMetadataInterface $productMetadata
     * @param ProductRepositoryInterface $productRepository
     * @param SourceCollectionFactory $sourceCollectionFactory
     * @param SourceItemsProcessor $sourceItemsProcessor
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        Config $config,
        DefaultSourceProviderInterface $defaultSourceProvider,
        FlagFactory $queueFlagFactory,
        NavisionInventory $navisionInventory,
        IsSingleSourceModeInterface $isSingleSourceMode,
        ProductMetadataInterface $productMetadata,
        ProductRepositoryInterface $productRepository,
        SourceCollectionFactory $sourceCollectionFactory,
        SourceItemsProcessor $sourceItemsProcessor,
        StockConfigurationInterface $stockConfiguration
    ) {
        $this->config = $config;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->navisionInventory = $navisionInventory;
        $this->productMetadata = $productMetadata;
        $this->productRepository = $productRepository;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->sourceCollectionFactory = $sourceCollectionFactory;
        $this->sourceItemsProcessor = $sourceItemsProcessor;
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * Import action
     *
     * @param $websiteId
     * @param int $navPageNumber
     *
     * @return bool|DataObject|Inventory
     *
     * @throws \Exception
     */
    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navisionInventory, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     *
     * @return void
     */
    public function updateInventory($data, $websiteId = 0): void
    {
        $this->importEntity($data, $websiteId);
    }

    /**
     * Import entity
     *
     * @param \SimpleXMLElement $data
     * @param $websiteId
     *
     * @return bool
     */
    public function importEntity(\SimpleXMLElement $data, $websiteId): bool
    {
        $sku = (string)$data->nav_item_id;
        $sku = trim($sku);
        if (empty($sku)) {
            $this->messages .= 'SKU is missing' . PHP_EOL;

            return false;
        }
        try {
            $product = $this->productRepository->get($sku, true, null, true);
            $priceIsUpdated = $this->updateProductPrice($product, $data, (int)$websiteId);
            $qtyIsUpdated = $this->updateProductQty($product, $data, (int)$websiteId);

            return $priceIsUpdated || $qtyIsUpdated;
        } catch (\Throwable $e) {
            $this->messages .= $sku . ': skipped - ' . $e->getMessage() . PHP_EOL;

            return false;
        }
    }

    /**
     * Update product price
     *
     * @param ProductInterface $product
     * @param \SimpleXMLElement $data
     * @param int $website
     *
     * @return bool
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws StateException
     */
    protected function updateProductPrice(ProductInterface $product, \SimpleXMLElement $data, int $website): bool
    {
        if (!$this->config->isInventoryUpdatePrice($website)) {
            return true;
        }
        if (!isset($data->unit_price)) {
            $this->messages .= $product->getSku() . ': price update skipped - ' . 'unit price is missing' . PHP_EOL;

            return false;
        }
        $price = (float)$data->unit_price;
        if ($product->getPrice() == $price) {
            $this->messages .= $product->getSku() . ': price not changed' . PHP_EOL;

            return true;
        }
        $product->setPrice($price);
        $this->productRepository->save($product);
        $this->messages .= $product->getSku() . ': price changed to ' . $price . PHP_EOL;

        return true;
    }

    /**
     * Update product qty
     *
     * @param ProductInterface $product
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     *
     * @return bool
     *
     * @throws NoSuchEntityException
     */
    protected function updateProductQty(ProductInterface $product, \SimpleXMLElement $data, int $websiteId): bool
    {
        if (isset($data->quantity)) {
            $quantity = (int)$data->quantity;
        } elseif (isset($data->item_qty_on_hand)) {
            $quantity = (int)$data->item_qty_on_hand;
        } else {
            $this->messages .= $product->getSku() . ': qty update skipped - ' . 'QTY is missing' . PHP_EOL;

            return false;
        }

        $stockStatus = (bool)$quantity;
        $globalIsManageStock = $this->stockConfiguration->getManageStock();
        $forcedInStock = $this->getConfig()->isInventoryInStockStatusMandatory($websiteId);

        /** @var StockItemInterface $stockItem */
        $stockItem = $product->getExtensionAttributes()->getStockItem();
        if (!$stockItem || !$stockItem->getId() || !$stockItem->getManageStock()
            || ($stockItem->getUseConfigManageStock() && !$globalIsManageStock)
        ) {
            $this->messages .= $product->getSku() . ': qty update skipped - stock for this product is not managed';

            return false;
        }

        // Magento >= 2.3.x logic
        if (version_compare($this->productMetadata->getVersion(), '2.3.0', '>=')
            && !$this->isSingleSourceMode->execute()
        ) {
            $sourceCodes = $this->getInventorySourceCodes($websiteId);
            if (empty($sourceCodes)) {
                $this->messages .= sprintf(
                    '%s: skipped - inventory sources not found for website id #%s' . PHP_EOL,
                    $product->getSku(),
                    $websiteId
                );

                return false;
            }
            $sourceItemQty = [];
            if (in_array($this->defaultSourceProvider->getCode(), $sourceCodes)) {
                $sourceItemQty[$this->defaultSourceProvider->getCode()] = $quantity;
            }
            foreach ($sourceCodes as $sourceCode) {
                $sourceCode = strtoupper($sourceCode);
                if (isset($data->$sourceCode)) {
                    $sourceItemQty[$sourceCode] = (int)$data->$sourceCode;
                }
                $sourceCode = strtolower($sourceCode);
                if (isset($data->$sourceCode)) {
                    $sourceItemQty[$sourceCode] = (int)$data->$sourceCode;
                }
            }
            $this->sourceItemsProcessor->process(
                $product,
                $sourceItemQty,
                $stockStatus && $forcedInStock ? true : null
            );
            $this->messages .= $product->getSku() . ': qty changed in Multi Source Inventory';

            return true;
        }

        // Magento <= 2.2.x logic
        if ($stockStatus && $forcedInStock) {
            $stockItem->setIsInStock($stockStatus);
        }
        $stockItem->setQty($quantity);
        $stockItem->save();

        $this->messages .= $product->getSku() . ': qty changed to ' . $quantity;

        return true;
    }

    /**
     * Get inventory source codes except default
     *
     * @param int $websiteId
     *
     * @return array
     */
    protected function getInventorySourceCodes(int $websiteId)
    {
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
