<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;

class Promotion extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'promo';
    const CACHE_ID = 'mconnect_promotion_price';
    const CACHE_TAG = 'mconnect_promotion';
    const NAV_XML_NODE_ITEM_NAME = 'item';

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
        \MalibuCommerce\MConnect\Model\Navision\Promotion $navPromotion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Store\Model\StoreManagerInterface $_storeManager,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        Json $serializer = null
    ) {
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

    public function getPromoPrice(\Magento\Catalog\Model\Product $product)
    {
        $websiteId = $this->_storeManager->getWebsite()->getWebsiteId();
        if(!(bool)$this->config->getWebsiteData(self::CODE . '/import_enabled', $websiteId)) {
            return false;
        }

        $lifeTime = $this->config->getWebsiteData(self::CODE . '/cache_lifetime', $websiteId);
        $promoPrice = 0;
        $cache = $this->cache->load(self::CACHE_ID.$product->getSku());
        if($cache != false && array_key_exists($product->getSku(), $this->serializer->unserialize($cache))) {
            $products = $this->serializer->unserialize($cache);
            $promoPrice = $products[$product->getSku()];
            return $promoPrice['price'];
        } else {
            $navPageNumber = 0;
            $this->processMagentoImport($this->navPromotion, $this, $websiteId, $navPageNumber);
        }
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $cache = $this->cache->load(self::CACHE_ID.$data->sku);
        if($cache != false) {
            $products = $this->serializer->unserialize($cache);
        } else {
            $products = [];
        }
        $products[$data->sku] = ['price' => $data->price, 'quantity' => $data->quantity];
        $this->cache->save($this->serializer->serialize($products), self::CACHE_ID.$data->sku, [self::CACHE_TAG], $lifeTime);

        return true;
    }

}