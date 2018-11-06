<?php

namespace MalibuCommerce\MConnect\Model;

class Pricerule extends \Magento\Framework\Model\AbstractModel
{
    protected $matchedPrices = [];

    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Pricerule');
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
        
        // attempt to get price match for default scope
        if (!$price === false && $websiteId != 0) {
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
            $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
            $price = $collection->matchDiscountPrice($sku, $qty, 0);
        }

        $this->matchedPrices[$cacheId] = $price;

        return $this->matchedPrices[$cacheId];
    }
}