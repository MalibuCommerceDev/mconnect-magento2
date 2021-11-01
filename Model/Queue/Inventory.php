<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Inventory\Model\ResourceModel\Source\Collection as SourceCollection;
use Magento\Inventory\Model\ResourceModel\Source\CollectionFactory as SourceCollectionFactory;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;

class Inventory extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'inventory';
    const NAV_XML_NODE_ITEM_NAME = 'item_inventory';

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Product|Inventory
     */
    protected $navInventory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config|Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var StockConfigurationInterface
     */
    protected $configuration;

    /**
     * @var DefaultSourceProviderInterface
     */
    protected $defaultSourceProvider;

    /**
     * @var IsSingleSourceModeInterface
     */
    protected $isSingleSourceMode;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var SourceCollectionFactory
     */
    protected $sourceCollectionFactory;

    /**
     * Inventory constructor.
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface      $productRepository
     * @param \MalibuCommerce\MConnect\Model\Navision\Inventory    $navInventory
     * @param \MalibuCommerce\MConnect\Model\Config                $config
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \MalibuCommerce\MConnect\Model\Navision\Inventory $navInventory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        DefaultSourceProviderInterface $defaultSourceProvider,
        IsSingleSourceModeInterface $isSingleSourceMode,
        ProductMetadataInterface $productMetadata,
        SourceCollectionFactory $sourceCollectionFactory,
        StockConfigurationInterface $configuration
    ) {
        $this->productRepository = $productRepository;
        $this->navInventory = $navInventory;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->configuration = $configuration;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->productMetadata = $productMetadata;
        $this->sourceCollectionFactory = $sourceCollectionFactory;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navInventory, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     */
    public function updateInventory($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $sku = (string)$data->nav_item_id;
        $sku = trim($sku);
        if (empty($sku)) {
            $this->messages .= 'SKU is missing' . PHP_EOL;
            return false;
        }

        try {
            /** @var ProductInterface $product */
            $product = $this->productRepository->get($sku, true, null, true);

            if (isset($data->quantity)) {
                $quantity = (int)$data->quantity;
            } elseif (isset($data->item_qty_on_hand)) {
                $quantity = (int)$data->item_qty_on_hand;
            } else {
                $this->messages .= $sku . ': ' . 'QTY is missing' . PHP_EOL;
                return false;
            }

            $stockStatus = (bool)$quantity;
            $globalIsManageStock = $this->configuration->getManageStock();
            $forcedInStock = $this->getConfig()->isInventoryInStockStatusMandatory($websiteId);

            /** @var \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem */
            $stockItem = $product->getExtensionAttributes()->getStockItem();
            if (!$stockItem || !$stockItem->getId() || !$stockItem->getManageStock()
                || ($stockItem->getUseConfigManageStock() && !$globalIsManageStock)
            ) {
                $this->messages .= $sku . ': skipped - stock for this product is not managed';

                return false;
            }

            // Magento >= 2.3.x logic
            if (version_compare($this->productMetadata->getVersion(), '2.3.0', '>=')
                && !$this->isSingleSourceMode->execute()
            ) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                /** @var \MalibuCommerce\MConnect\Model\Queue\Inventory\SourceItemsProcessor $inventoryProcessor */
                $inventoryProcessor = $objectManager->create(
                    '\MalibuCommerce\MConnect\Model\Queue\Inventory\SourceItemsProcessor'
                );
                $sourceCodes = $this->getInventorySourceCodes($websiteId);
                if (empty($sourceCodes)) {
                    $this->messages .= $sku . ': skipped - inventory sources not found for website id #' . $websiteId;

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
                }

                $inventoryProcessor->process($product, $sourceItemQty, $stockStatus && $forcedInStock ? true : null);

                return true;
            }

            // Magento <= 2.2.x logic
            if ($stockStatus && $forcedInStock) {
                $stockItem->setIsInStock($stockStatus);
            }
            $stockItem->setQty($quantity);
            $stockItem->save();

            $this->messages .= $sku . ': qty changed to ' . $quantity;
        } catch (\Throwable $e) {
            $this->messages .= $sku . ': skipped - ' . $e->getMessage() . PHP_EOL;

            return false;
        }

        return true;
    }

    /**
     * Get inventory source codes except default
     *
     * @param $websiteId
     *
     * @return array
     */
    protected function getInventorySourceCodes($websiteId)
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
