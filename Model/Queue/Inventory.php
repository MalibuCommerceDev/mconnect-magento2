<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;

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
        StockConfigurationInterface $configuration
    ) {
        $this->productRepository = $productRepository;
        $this->navInventory = $navInventory;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->configuration = $configuration;
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
            if (class_exists(\Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface::class)) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                /** @var \MalibuCommerce\MConnect\Model\Queue\Inventory\SourceItemsProcessor $inventoryProcessor */
                $inventoryProcessor = $objectManager->create(
                    '\MalibuCommerce\MConnect\Model\Queue\Inventory\SourceItemsProcessor'
                );

                $inventoryProcessor->process($product, $quantity, $stockStatus && $forcedInStock ? true : null);

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
}
