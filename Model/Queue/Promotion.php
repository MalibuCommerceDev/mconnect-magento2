<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\CacheInterface;
use Magento\Customer\Model\SessionFactory;
use MalibuCommerce\MConnect\Helper\Customer;
use MalibuCommerce\MConnect\Model\Queue;
use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Model\QueueFactory;

class Promotion extends Queue implements ImportableEntity
{
    const CODE                            = 'promotion';
    const NAV_XML_NODE_ITEM_NAME          = 'items';

    const CACHE_ID_PREFIX                 = 'mconnect_promo_';
    const CACHE_TAG                       = 'mconnect_promotion';

    /** @var Customer */
    protected $customerHelper;

    /** @var \MalibuCommerce\MConnect\Model\Navision\Promotion */
    protected $navPromotion;

    /** @var Config */
    protected $config;

    /** @var FlagFactory */
    protected $queueFlagFactory;

    /** @var QueueFactory */
    protected $queueFactory;

    /** @var CacheInterface */
    protected $cacheInstance;

    /** @var array */
    protected $arrayCache = [];

    /** @var Json */
    protected $serializer;

    /** @var bool */
    protected $promoFeatureEnabled = false;

    /**
     * Promotion constructor.
     *
     * @param Registry                                          $registry
     * @param \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion
     * @param Config                                            $config
     * @param CacheInterface                                    $cache
     * @param Json                                              $serializer
     * @param SessionFactory                                    $customerSessionFactory
     * @param FlagFactory                                       $queueFlagFactory
     * @param QueueFactory                                      $queueFactory
     * @param Customer                                          $customerHelper
     */
    public function __construct(
        Registry $registry,
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        Config $config,
        CacheInterface $cache,
        Json $serializer,
        SessionFactory $customerSessionFactory,
        FlagFactory $queueFlagFactory,
        QueueFactory $queueFactory,
        Customer $customerHelper
    ) {
        $this->registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->cacheInstance = $cache;
        $this->serializer = $serializer;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->queueFactory = $queueFactory;
        $this->customerHelper = $customerHelper;
    }

    /**
     * @param int $websiteId
     * @param int $navPageNumber
     *
     * @return bool|DataObject|Promotion
     * @throws \Exception
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
        $promoPriceData = [];
        foreach ($data->item as $item) {
            if (isset($item->price)) {
                $promoPriceData[(int)$item->quantity] = (float)$item->price;
            }
        }
        if (count($promoPriceData) <= 0) {
            $promoPriceData = [1 => 'NULL'];
        }

        $this->savePriceCache($promoPriceData, (string)$item->sku, $websiteId);

        return true;
    }

    /**
     * @param string $sku
     * @param int|null $qty if null, return all cached prices for specified SKU
     *
     * @return bool
     */
    public function getCachedPrice($sku, $qty = 1)
    {
        $cacheId = $this->getCacheId($sku);
        if (key_exists($cacheId, $this->arrayCache)) {
            $cacheData = $this->arrayCache[$cacheId];
            if ($qty === null) {

                return $cacheData;
            }

            foreach ($cacheData as $promoPriceQty => $promoPricePrice) {
                if ($qty >= $promoPriceQty) {

                    return $promoPricePrice;
                }
            }
        } else {
            $cacheData = $this->cacheInstance->load($cacheId);
            if ($cacheData != false) {
                $cacheData = $this->serializer->unserialize($cacheData);
                $this->arrayCache[$cacheId] = $cacheData;
                if ($qty === null) {

                    return $cacheData;
                }

                foreach ($cacheData as $promoPriceQty => $promoPricePrice) {
                    if ($qty >= $promoPriceQty) {

                        return $promoPricePrice;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array  $promoPriceData
     * @param string $sku
     * @param int    $websiteId
     */
    public function savePriceCache(array $promoPriceData, $sku, $websiteId = 0)
    {
        $cacheId = $this->getCacheId($sku);
        $lifeTime = $this->config->getWebsiteData(self::CODE . '/price_ttl', $websiteId);
        krsort($promoPriceData);

        $this->arrayCache[$cacheId] = $promoPriceData;
        $this->cacheInstance->save(
            $this->serializer->serialize($promoPriceData),
            $cacheId,
            [self::CACHE_TAG],
            $lifeTime
        );
    }

    /**
     * @param \Magento\Catalog\Model\Product|string $product
     * @param int                                   $qtyToCheck if null, return all cached prices for specified SKU
     * @param int                                   $websiteId
     *
     * @return bool|float|null
     */
    public function matchPromoPrice($product, $qtyToCheck = 1, $websiteId = 0)
    {
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {

            return false;
        }
        $requestedQty = $qtyToCheck;
        $qtyToCheck = max(1, $qtyToCheck);

        if (is_string($product)) {
            $sku = $product;
        } else {
            $sku = $product->getSku();
        }

        $promoPrice = $this->getCachedPrice($sku, $requestedQty);
        // "NULL" means no price is available and this fact was cached
        if ($promoPrice == 'NULL') {

            return false;
        }
        if (!empty($promoPrice)) {

            return $promoPrice;
        }

        $productsSkuToQtyMap = [$sku => $qtyToCheck];
        try {
            $this->requestPriceData($productsSkuToQtyMap, $websiteId);
        } catch (\Throwable $e) {
            $this->savePriceCache([1 => 'NULL'], $sku, $websiteId);
        }

        $promoPrice = $this->getCachedPrice($sku, $requestedQty);
        // "NULL" means no price is available and this fact was cached
        if ($promoPrice == 'NULL') {

            return false;
        }

        return $promoPrice;
    }

    /**
     * @param array $productsSkuToQtyMap
     * @param int   $websiteId
     *
     * @return bool|DataObject|Promotion
     * @throws \Exception
     */
    public function requestPriceData(array $productsSkuToQtyMap, $websiteId = 0)
    {
        if (empty($productsSkuToQtyMap)) {

            return false;
        }
        $this->navPromotion->setRequestedProducts($productsSkuToQtyMap);
        return $this->processMagentoImport($this->navPromotion, $this, $websiteId);
    }

    /**
     * @param $sku
     *
     * @return string
     */
    protected function getCacheId($sku)
    {
        $func = 'md' . '4';
        $func++;
        return sprintf('%s%s__%s', self::CACHE_ID_PREFIX, $this->customerHelper->getCurrentCustomerId(), $func($sku));
    }
}
