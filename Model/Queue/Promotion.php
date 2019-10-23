<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Customer\Model\Group;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\CacheInterface;
use Magento\Customer\Model\SessionFactory;
use MalibuCommerce\MConnect\Model\Queue;
use MalibuCommerce\MConnect\Model\Config;

class Promotion extends Queue implements ImportableEntity
{
    const CODE                            = 'promotion';
    const CACHE_ID_PREFIX                 = 'mconnect_promo_';
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
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $cache;

    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $customerSessionFactory;

    /**
     * @var int
     */
    protected $customerId = null;

    public function __construct(
        Registry $registry,
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        Config $config,
        CacheInterface $cache,
        Json $serializer,
        SessionFactory $customerSessionFactory
    ) {
        $this->registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->cache = $cache;
        $this->customerSessionFactory = $customerSessionFactory;
        $this->serializer = $serializer;
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
     * @param int               $websiteId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @param $sku
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCacheId($sku)
    {
        return sprintf('%s%s__%s', self::CACHE_ID_PREFIX, $this->getCustomerId(), md5($sku));
    }

    /**
     * Return logged in customer ID
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface|\Magento\Customer\Model\Customer|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomerId()
    {
        if ($this->customerId === null) {
            /** @var \Magento\Customer\Model\Session $customer */
            $customer = $this->customerSessionFactory->create();
            if ($customer->getCustomer() && $customer->getCustomer()->getId()) {
                $this->customerId = $customer->getCustomer()->getId();
            } else {
                $this->customerId = Group::NOT_LOGGED_IN_ID;
            }
        }

        return $this->customerId;
    }

    /**
     * @param \Magento\Catalog\Model\Product|string $product
     * @param int                                   $qty
     * @param int                                   $websiteId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @param     $sku
     * @param int $qty
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @param array  $productPromoInfo
     * @param string $sku
     * @param int    $websiteId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @param int    $websiteId
     *
     * @return array|bool|float|int|mixed|string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @return array|bool|float|int|mixed|string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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