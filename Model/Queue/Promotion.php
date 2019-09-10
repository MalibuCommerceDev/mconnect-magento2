<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class Promotion extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'promotion';
    const CACHE_ID = 'mconnect_promotion_price';
    const CACHE_TAG = 'mconnect_promotion';
    const REGISTRY_KEY_NAV_PROMO_PRODUCTS = 'mconnect_promotion';
    const NAV_XML_NODE_ITEM_NAME = 'item';

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
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

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

    protected $serializer;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Store\Model\StoreManagerInterface $_storeManager,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        Json $serializer = null
    ) {
        $this->registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->date = $date;
        $this->_storeManager = $_storeManager;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->cache = $cache;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);

    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
    }

    public function getPromoPrice(\Magento\Catalog\Model\Product $product, $qty = 1)
    {
        $websiteId = $this->_storeManager->getWebsite()->getWebsiteId();
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {
            return false;
        }

        if ($promoPrice = $this->getPriceFromCache($product, $qty)) {
            return $promoPrice;
        } else {
            $prepareProducts = $this->registry->registry(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
            $prepareProducts[$product->getSku()] = $qty;

            $this->registry->unregister(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
            $this->registry->register(self::REGISTRY_KEY_NAV_PROMO_PRODUCTS, $prepareProducts);
            $navPageNumber = 0;
            $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
            return $this->getPriceFromCache($product, $qty);
        }
        return false;
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        if (isset($data->price)){
            $productPromoInfo = ['price' => (float)$data->price, 'quantity' => (int)$data->quantity];
            $lifeTime = $this->config->getWebsiteData(self::CODE . '/price_ttl', $websiteId);
            $this->cache->save(
                $this->serializer->serialize($productPromoInfo),
                $this->getCacheId((string)$data->sku, (int)$data->quantity),
                [self::CACHE_TAG], $lifeTime
            );
        }
        return true;
    }

    public function getCacheId($sku, $qty)
    {
        return self::CACHE_ID.$sku.'_'.$qty;
    }

    public function getPriceFromCache(\Magento\Catalog\Model\Product $product, $qty = 1)
    {
        $cache = $this->cache->load($this->getCacheId($product->getSku(), $qty));
        if (($qty > 1) && ($cache == false)) {
            $cache = $this->cache->load($this->getCacheId($product->getSku(), 1));
        }
        if ($cache != false) {
            $productPromoInfo = $this->serializer->unserialize($cache);
            if(isset($productPromoInfo['quantity']) && isset($productPromoInfo['price'])){
                if ($qty >= $productPromoInfo['quantity']) {
                    return $productPromoInfo['price'];
                }
            }
        }
        return false;
    }
}