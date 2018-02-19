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

    /** @var \Psr\Log\LoggerInterface $logger */
    protected $logger;

    /**
     * @var Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $_stockStateInterface;

    /**
     * @var Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $_stockRegistry;

    /**
     * Inventory constructor.
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \MalibuCommerce\MConnect\Model\Navision\Inventory $navInventory
     * @param \MalibuCommerce\MConnect\Model\Config $config
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Api\StockStateInterface $stockStateInterface,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \MalibuCommerce\MConnect\Model\Navision\Inventory $navInventory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory
    )
    {
        $this->productRepository = $productRepository;
        $this->_stockStateInterface = $stockStateInterface;
        $this->_stockRegistry = $stockRegistry;
        $this->navInventory = $navInventory;
        $this->config = $config;
        $this->logger = $logger;
        $this->queueFlagFactory = $queueFlagFactory;
    }

    public function importAction()
    {
        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME);
        $result = false;
        do {
            try {
                $result = $this->navInventory->export($page++, $lastUpdated);
                //$this->logger->info("result: " . print_r($result, 1));
                foreach ($result->item as $data) {
                    $count++;
                    $import = $this->_importInventory($data);
                    $this->messages .= PHP_EOL;
                }
                if (!$lastSync) {
                    $lastSync = $result->status->current_date_time;
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage();
            }
        } while ($result && isset($result->status->end_of_records) && (string)$result->status->end_of_records === 'false');
        $this->setLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME, $lastSync);
        $this->messages .= PHP_EOL . 'Processed ' . $count . ' inventory(ies).';
    }

    protected function _importInventory($data)
    {
        $sku = trim($data->nav_item_id);
        if (empty($sku)) {
            return false;
        }

        try {
            $quantity = (int) $data->quantity;
            $stockItem = $this->_stockRegistry->getStockItemBySku($sku);
            if ((bool) $stockItem->getData('manage_stock')) {
                $stockItem->setData('is_in_stock', ($quantity > 0));
                $stockItem->setData('qty', $quantity);
            }
            $stockItem->save();
            $this->_messages[] = $sku . ' qty changed to ' . $qty;
        } catch (\Exception $e) {
            $this->messages .= $sku . ': ' . $e->getMessage();
            return false;
        }

    }
}