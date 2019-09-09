<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class Promotion extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'promotion';
    const CACHE_ID = 'mconnect_promotion_price';
    const CACHE_TAG = 'mconnect_promotion';
    const NAV_XML_NODE_ITEM_NAME = 'item';

    /**
     * @var \Magento\Framework\Registry
     */

    protected $_registry;

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
        $this->_registry = $registry;
        $this->navPromotion = $navPromotion;
        $this->config = $config;
        $this->date = $date;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->cache = $cache;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);

    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param int                            $qty
     * @param int                            $websiteId
     *
     * @return bool
     */
    public function getPromoPrice(\Magento\Catalog\Model\Product $product, $qty = 1, $websiteId = 0)
    {
        if (!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {

            return false;
        }

        if ($promoPrice = $this->getPriceFromCache($product, $qty)) {

            return $promoPrice;
        }

        $prepareProducts = $this->_registry->registry(self::CACHE_TAG);
        $prepareProducts[$product->getSku()] = $qty;
        $this->_registry->unregister(self::CACHE_TAG);
        $this->_registry->register(self::CACHE_TAG, $prepareProducts);
        $navPageNumber = 0;
        $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);

        return $this->getPriceFromCache($product, $qty);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        if (isset($data->price)){
            $productPromoInfo = ['price' => (float)$data->price, 'quantity' => (int)$data->quantity];
            $lifeTime = $this->config->getWebsiteData(self::CODE . '/price_ttl', $websiteId);
            $this->cache->save($this->serializer->serialize($productPromoInfo), self::CACHE_ID.(string)$data->sku, [self::CACHE_TAG], $lifeTime);
        }
        return true;
    }

    public function getPriceFromCache(\Magento\Catalog\Model\Product $product, $qty = 1)
    {
        $cache = $this->cache->load(self::CACHE_ID.$product->getSku());
        if ($cache != false) {
            $productPromoInfo = $this->serializer->unserialize($cache);
            if ($qty >= $productPromoInfo['quantity']) {
                return $productPromoInfo['price'];
            }
        }
        return false;
    }
}