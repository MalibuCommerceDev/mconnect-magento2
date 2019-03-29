<?php

namespace MalibuCommerce\MConnect\Model;

use MalibuCommerce\MConnect\Model\Resource\Pricerule as RuleResourceModel;

class Pricerule extends \Magento\Framework\Model\AbstractModel
{
    protected $matchedPrices = [];

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Store\Api\WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Api\WebsiteRepositoryInterface $websiteRepository,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->config = $config;
        $this->websiteRepository = $websiteRepository;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function _construct()
    {
        $this->_init(RuleResourceModel::class);
    }

    /**
     * Match and retrieve discount price by specified product and QTY
     *
     * @param \Magento\Catalog\Model\Product|string $product
     * @param int $qty
     * @param int $websiteId
     *
     * @return string|bool
     */
    public function matchDiscountPrice($product, $qty, $websiteId = 0)
    {
        if (!$this->config->isModuleEnabled()) {

            return false;
        }

        $sku = $product;
        if ($product instanceof \Magento\Catalog\Model\Product) {
            $sku = $product->getSku();
        }
        $qty = max(1, $qty);

        $cacheId = md5($sku . $qty);
        if (array_key_exists($cacheId, $this->matchedPrices)) {

            return $this->matchedPrices[$cacheId];
        }

        /** @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $collection */
        $collection = $this->getResourceCollection();
        $price = $collection->matchDiscountPrice($sku, $qty, $websiteId);
        
        // If current website is a default website, then attempt to get price match for default scope (Website ID = 0)
        if ($price === false && $websiteId == $this->websiteRepository->getDefault()->getId()) {
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
            $price = $collection->matchDiscountPrice($sku, $qty, 0);
        }

        $this->matchedPrices[$cacheId] = $price;

        return $this->matchedPrices[$cacheId];
    }
}