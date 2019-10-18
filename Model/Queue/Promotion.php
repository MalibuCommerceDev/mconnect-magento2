<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class Promotion extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE                            = 'promotion';
    const CACHE_ID                        = 'mconnect_promotion_price';
    const CACHE_TAG                       = 'mconnect_promotion';
    const REGISTRY_KEY_NAV_PROMO_PRODUCTS = 'mconnect_promotion';
    const NAV_XML_NODE_ITEM_NAME          = 'items';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Promotion
     */
    protected $navPromotion;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * Date
     *
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var Json
     */
    protected $serializer;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        Json $serializer = null
    ) {
        $this->registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->date = $date;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->cache = $cache;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ? : ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * Import prices from NAV
     *
     * @param int $websiteId
     * @param int $navPageNumber
     *
     * @return bool|\Magento\Framework\DataObject|Promotion
     */
    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
    }

    /**
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     *
     * @return bool
     */
    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $productPromoInfo = [];
        foreach ($data->item as $item) {
            if (isset($item->price)) {
                $productPromoInfo[(int)$item->quantity] = (float)$item->price;
            }
        }
        if (count($productPromoInfo) > 0) {
            $this->savePromoPriceToCache($productPromoInfo, (string)$item->sku, $websiteId);
        }

        return true;
    }

    /**
     * @param string $sku
     *
     * @return string
     */
    public function getCacheId($sku)
    {
        return self::CACHE_ID . $sku;
    }

    /**
     * @param \Magento\Catalog\Model\Product|string $product
     * @param int                                   $qty
     * @param int                                   $websiteId
     *
     * @return bool|float
     */
    public function getPromoPrice($product, $qty = 1, $websiteId = 0)
    {
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {

            return false;
        }

        if (is_string($product)) {
            $sku = $product;
        } else {
            $sku = $product->getSku();
        }

        $promoPrice = $this->getPriceFromCache($sku, $qty);
        if ($promoPrice == 'NULL') {

            return false;
        }
        if (!empty($promoPrice)) {

            return $promoPrice;
        }

        $prepareProducts = $this->registry->registry(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $prepareProducts[$sku] = $qty;
        $this->registry->unregister(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $this->registry->register(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS, $prepareProducts);
        $navPageNumber = 0;
        $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);

        return $this->getPriceFromCache($sku, $qty);
    }

    /**
     * @param string $sku
     * @param int $qty
     *
     * @return bool|float
     */
    public function getPriceFromCache($sku, $qty = 1)
    {
        $cache = $this->cache->load($this->getCacheId($sku));
        if ($cache != false) {
            $productPromoInfo = $this->serializer->unserialize($cache);
            krsort($productPromoInfo);
            foreach ($productPromoInfo as $promoPriceQty => $promoPricePrice) {
                if ($qty >= $promoPriceQty) {

                    return $promoPricePrice;
                }
            }
        }

        return false;
    }

    /**
     * @param array $productPromoInfo
     * @param string $sku
     * @param int $websiteId
     */
    public function savePromoPriceToCache($productPromoInfo, $sku, $websiteId = 0)
    {
        $lifeTime = $this->config->getWebsiteData(self::CODE . '/price_ttl', $websiteId);
        $this->cache->save(
            $this->serializer->serialize($productPromoInfo),
            $this->getCacheId($sku),
            [self::CACHE_TAG], $lifeTime
        );
    }

    /**
     * @param string $sku
     * @param int $websiteId
     *
     * @return array|bool|null
     */
    public function getAllPromoPrices($sku, $websiteId = 0)
    {
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {

            return false;
        }

        $allPromoPrices = $this->getAllPricesFromCache($sku);
        if ($allPromoPrices == 'NULL') {

            return false;
        }
        if (!empty($allPromoPrices)) {

            return $allPromoPrices;
        }

        $prepareProducts = $this->registry->registry(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $prepareProducts[$sku] = 1;
        $this->registry->unregister(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        $this->registry->register(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS, $prepareProducts);
        $navPageNumber = 0;
        $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);

        return $this->getAllPricesFromCache($sku);
    }

    /**
     * @param string $sku
     *
     * @return array|bool|null
     */
    public function getAllPricesFromCache($sku)
    {
        $cache = $this->cache->load($this->getCacheId($sku));
        if ($cache != false) {
            $productPromoInfo = $this->serializer->unserialize($cache);
            ksort($productPromoInfo);

            return $productPromoInfo;
        }

        return false;
    }
}