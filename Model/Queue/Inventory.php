<?php

namespace MalibuCommerce\MConnect\Model\Queue;

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
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $_stockStateInterface;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $_stockRegistry;

    /**
     * Inventory constructor.
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface      $productRepository
     * @param \Magento\CatalogInventory\Api\StockStateInterface    $stockStateInterface ,
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \MalibuCommerce\MConnect\Model\Navision\Inventory    $navInventory
     * @param \MalibuCommerce\MConnect\Model\Config                $config
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \MalibuCommerce\MConnect\Model\Navision\Inventory $navInventory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory
    ) {
        $this->productRepository = $productRepository;
        $this->_stockStateInterface = $stockStateInterface;
        $this->_stockRegistry = $stockRegistry;
        $this->navInventory = $navInventory;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
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
            if (isset($data->quantity)) {
                $quantity = (int)$data->quantity;
            } elseif (isset($data->item_qty_on_hand)) {
                $quantity = (int)$data->item_qty_on_hand;
            } else {
                $this->messages .= $sku . ': ' . 'QTY is missing' . PHP_EOL;
                return false;
            }

            $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
            if ((bool)$stockItem->getData('manage_stock')) {
                $stockItem->setData('is_in_stock', ($quantity > 0));
                $stockItem->setData('qty', $quantity);
                $stockItem->save();
                $this->messages .= $sku . ': qty changed to ' . $quantity;
            } else {
                $this->messages .= $sku . ': skipped';
            }
        } catch (\Throwable $e) {
            $this->messages .= $sku . ': ' . $e->getMessage() . PHP_EOL;

            return false;
        }

        return true;
    }
}