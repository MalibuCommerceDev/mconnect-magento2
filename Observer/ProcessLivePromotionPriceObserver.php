<?php

namespace MalibuCommerce\MConnect\Observer;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use MalibuCommerce\MConnect\Model\Queue\Promotion;

class ProcessLivePromotionPriceObserver implements ObserverInterface
{
    /**
     * @var Promotion
     */
    protected $promotion;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var array
     */
    protected $requestedProducts = [];

    public function __construct(
        ResourceConnection $resourceConnection,
        Promotion $promotion,
        StoreManagerInterface $storeManager
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->promotion = $promotion;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve Live Promo Prices for products collection and cache them
     *
     * @param Observer $observer
     *
     * @return $this|void
     * @throws \Throwable
     */
    public function execute(Observer $observer)
    {
        if (!$this->promotion->getConfig()->isModuleEnabled()) {

            return $this;
        }

        /* @var $collection Collection */
        $collection = $observer->getEvent()->getCollection();

        $websiteId = $this->storeManager->getStore($collection->getStoreId())->getWebsiteId();
        $promoEnabled = (bool)$this->promotion->getConfig()->getWebsiteData(
            Promotion::CODE . '/import_enabled',
            $websiteId
        );

        if (!$promoEnabled) {

            return $this;
        }

        if ($collection->getSize() <= 0) {

            return $this;
        }

        $this->requestedProducts = [];
        $simpleProducts = [];

        foreach ($collection as $product) {
            if (!$this->promotion->getCachedPrice($product->getSku())) {
                $this->requestedProducts[$product->getSku()] = 1;
            }
            if ($product->getTypeId() == Configurable::TYPE_CODE) {
                $simpleProducts[] = $product->getId();
            }
        }
        $this->prepareSimpleProducts($simpleProducts);

        if (!empty($this->requestedProducts)) {
            try {
                $this->promotion->requestPriceData($this->requestedProducts, $websiteId);
            } catch (\Throwable $e) {
                foreach ($this->requestedProducts as $sku => $qty) {
                    $this->promotion->savePriceCache([1 => 'NULL'], $sku, $websiteId);
                }
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
                if (!$this->promotion->getCachedPrice($simpleProduct['sku'])) {
                    $this->requestedProducts[$simpleProduct['sku']] = 1;
                }
            }
        }
    }
}
