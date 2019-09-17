<?php

namespace MalibuCommerce\MConnect\Observer;

class ProcessLivePromotionPriceObserver implements \Magento\Framework\Event\ObserverInterface
{
    const PRODUCT_QTY_FOR_IMPORT = 1;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Promotion
     */
    protected $promotion;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var array
     */
    protected $collectedProducts = [];

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * ProcessLivePromotionPriceObserver constructor.
     *
     * @param \Magento\Framework\Registry                    $registry
     * @param \Magento\Framework\App\ResourceConnection      $resourceConnection
     * @param \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion
     * @param \Magento\Store\Model\StoreManagerInterface     $storeManager
     * @param \MalibuCommerce\MConnect\Model\Config          $config
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \MalibuCommerce\MConnect\Model\Config $config
    ) {
        $this->registry = $registry;
        $this->resourceConnection = $resourceConnection;
        $this->promotion = $promotion;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * Retrieve Live Promo Prices for products collection and cache them
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /* @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
        $collection = $observer->getEvent()->getCollection();

        $websiteId = $this->storeManager->getStore($collection->getStoreId())->getWebsiteId();
        $promoEnabled = $this->config->getWebsiteData(
            \MalibuCommerce\MConnect\Model\Queue\Promotion::CODE . '/import_enabled',
            $websiteId
        );

        if (!(bool)$promoEnabled) {

            return $this;
        }

        if ($collection->getSize() <= 0) {

            return $this;
        }
        
        unset($this->collectedProducts);
        $this->collectedProducts = [];
        $simpleProducts = [];
        $productsRegistryKey = \MalibuCommerce\MConnect\Model\Queue\Promotion::REGISTRY_KEY_NAV_PROMO_PRODUCTS;
        
        foreach ($collection as $product) {
            if (!$this->promotion->getPriceFromCache($product->getSku(), self::PRODUCT_QTY_FOR_IMPORT)) {
                $this->collectedProducts[$product->getSku()] = self::PRODUCT_QTY_FOR_IMPORT;
            }
            if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                $simpleProducts[] = $product->getId();
            }
        }
        $this->prepareSimpleProducts($simpleProducts);

        if (count($this->collectedProducts) > 0) {
            $this->registry->unregister($productsRegistryKey);
            $this->registry->register($productsRegistryKey, $this->collectedProducts);
            $store = $this->storeManager->getStore($collection->getStoreId());
            $websiteId = $store->getWebsiteId();
            $this->promotion->runMultiplePromoPriceImport($websiteId);
        }

        //Save products without promo price to cache
        foreach ($this->collectedProducts as $itemKey => $itemValue) {
            if (!$this->promotion->getPriceFromCache($itemKey, self::PRODUCT_QTY_FOR_IMPORT)) {
                $this->promotion->savePromoPriceToCache(
                    [
                        'price'    => 'NULL',
                        'quantity' => self::PRODUCT_QTY_FOR_IMPORT
                    ],
                    $itemKey,
                    $websiteId
                );
            }
        }

        return $this;
    }

    /**
     * @param $simpleProducts
     */
    public function prepareSimpleProducts($simpleProducts)
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(
            ['product' => $connection->getTableName('catalog_product_entity')],
            ['entity_id', 'sku']
        );
        $select->joinInner(
            ['link_table' => 'catalog_product_super_link'],
            'product.entity_id = link_table.product_id',
            []
        );
        $select->where('link_table.parent_id IN (?)', $simpleProducts);

        $simpleProducts = $connection->fetchAssoc($select);
        if (count($simpleProducts) > 0) {
            foreach ($simpleProducts as $simpleProduct) {
                if (!$this->promotion->getPriceFromCache($simpleProduct['sku'], self::PRODUCT_QTY_FOR_IMPORT)) {
                    $this->collectedProducts[$simpleProduct['sku']] = self::PRODUCT_QTY_FOR_IMPORT;
                }
            }
        }
    }
}