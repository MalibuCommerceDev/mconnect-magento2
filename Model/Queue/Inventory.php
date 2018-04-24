<?php

namespace MalibuCommerce\MConnect\Model\Queue;

class Inventory extends \MalibuCommerce\MConnect\Model\Queue
{
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

    public function importAction()
    {
        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_INVENTORY_SYNC_TIME);
        $result = false;
        do {
            try {
                $result = $this->navInventory->export($page++, $lastUpdated);
                foreach ($result->item_inventory as $data) {
                    $importResult = $this->updateInventory($data);
                    if ($importResult) {
                        $count++;
                    }
                    if ($importResult === false) {
                        $this->messages .= 'Unable to import NAV inventory data' . PHP_EOL;
                    }
                    $this->messages .= PHP_EOL;
                }
                if (!$lastSync) {
                    $lastSync = $result->status->current_date_time;
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage() . PHP_EOL;
            }
        } while ($result && isset($result->status->end_of_records) && (string)$result->status->end_of_records === 'false');
        if ($count > 0) {
            $this->setLastSyncTime(Flag::FLAG_CODE_LAST_INVENTORY_SYNC_TIME, $lastSync);
            $this->messages .= PHP_EOL . 'Successfully processed ' . $count . ' NAV records(s).';
        } else {
            $this->messages .= PHP_EOL . 'Nothing to import.';
        }
    }

    public function updateInventory($data)
    {
        if (empty($data->nav_item_id)) {
            return false;
        }
        $sku = trim($data->nav_item_id);

        try {
            $quantity = (int)$data->quantity;
            $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
            if ((bool)$stockItem->getData('manage_stock')) {
                $stockItem->setData('is_in_stock', ($quantity > 0));
                $stockItem->setData('qty', $quantity);
                $stockItem->save();
                $this->messages .= $sku . ' qty changed to ' . $quantity;
            } else {
                $this->messages .= $sku . ': skipped';
            }
        } catch (\Exception $e) {
            $this->messages .= $sku . ': ' . $e->getMessage();

            return false;
        }

        return true;
    }
}