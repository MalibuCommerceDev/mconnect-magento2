<?php

namespace MalibuCommerce\MConnect\Observer;

class AddPromotionPrice implements \Magento\Framework\Event\ObserverInterface
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    protected $prepareProducts = [];

    /**
     * PromotionPlugin constructor.
     *
     * @param \Magento\Framework\Registry                               $registry
     * @param \Magento\ConfigurableProduct\Api\LinkManagementInterface  $linkManagement
     * @param \Magento\Framework\App\ResourceConnection                 $resourceConnection
     * @param \MalibuCommerce\MConnect\Model\Queue\Promotion            $promotion
     * @param \Magento\Store\Model\StoreManagerInterface                $storeManager
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \Magento\ConfigurableProduct\Api\LinkManagementInterface $linkManagement,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->registry = $registry;
        $this->linkManagement = $linkManagement;
        $this->resourceConnection = $resourceConnection;
        $this->promotion = $promotion;
        $this->storeManager = $storeManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $collection = $observer->getEvent()->getCollection();
        /* @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
        if ($collection->getSize() > 0 ) {
            $key = \MalibuCommerce\MConnect\Model\Queue\Promotion::REGISTRY_KEY_NAV_PROMO_PRODUCTS;
            $simpleProducts = [];
            foreach ($collection as $product) {
                $this->prepareProducts[$product->getSku()] = self::PRODUCT_QTY_FOR_IMPORT;
                if ($product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                    $simpleProducts[] = $product->getId();
                }
            }
            $this->loadSimpleProducts($simpleProducts);
            $this->registry->unregister($key);
            $this->registry->register($key, $this->prepareProducts);

            $store = $this->storeManager->getStore($collection->getStoreId());
            $websiteId = $store->getWebsiteId();
            $this->promotion->runMultiplePromoPriceImport($websiteId);

            //Save products without promo price to cache
            foreach ($this->prepareProducts as $itemKey => $itemValue) {
                if (!$this->promotion->getPriceFromCache($itemKey, self::PRODUCT_QTY_FOR_IMPORT)) {
                    $this->promotion->savePromoPriceToCache(['price' => 'NULL', 'quantity' => self::PRODUCT_QTY_FOR_IMPORT], $itemKey, $websiteId);
                }
            }
        }
        return $this;
    }

    public function loadSimpleProducts($simpleProducts)
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
                $this->prepareProducts[$simpleProduct['sku']] = self::PRODUCT_QTY_FOR_IMPORT;
            }
        }
    }
}