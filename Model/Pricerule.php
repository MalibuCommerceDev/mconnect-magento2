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
     *
     * @return string|bool
     */
    public function matchDiscountPrice($product, $qty)
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
        $this->matchedPrices[$cacheId] = $collection->matchDiscountPrice($sku, $qty);

        return $this->matchedPrices[$cacheId];
    }
}