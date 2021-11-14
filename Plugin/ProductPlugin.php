<?php

namespace MalibuCommerce\MConnect\Plugin;

use Magento\Catalog\Model\Product;
use MalibuCommerce\MConnect\Model\Pricerule;
use MalibuCommerce\MConnect\Model\Queue\Promotion;

/**
 * Used to override product price on PLP
 */
class ProductPlugin
{
    /**
     * @var Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Promotion
     */
    protected $promotion;

    /**
     * Product plugin constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param Pricerule $rule
     * @param Promotion $promotion
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Pricerule $rule,
        Promotion $promotion
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
        $this->promotion = $promotion;
    }

    /**
     * Plugin to apply MConnect Price Rules for Product
     *
     * @param Product $product
     * @param float $originalSpecialPrice
     *
     * @return float|null
     */
    public function afterGetPrice(Product $product, $originalFinalPrice)
    {
        if (!$this->promotion->getConfig()->isModuleEnabled()) {

            return $originalFinalPrice;
        }

        try {
            $websiteId = $product->getStore()->getWebsiteId();
            $qty = $product->getQty();
            $qty = max(1, $qty);

            $mconnectPrice = $this->promotion->matchPromoPrice($product, $qty, $websiteId);

            if ($mconnectPrice === false) {
                $mconnectPrice = $this->rule->matchDiscountPrice($product, $qty, $websiteId);
            }

            if ($mconnectPrice === false) {

                return $originalFinalPrice;
            }
            if (!empty($product->hasData('final_price')) && $mconnectPrice >= $product->getData('final_price')) {

                return $originalFinalPrice;
            }
            if ($this->promotion->getConfig()->isDisplayRegularPrice()) {
                $product->setSpecialPrice($mconnectPrice);

                return $originalFinalPrice;
            }

            return $mconnectPrice;
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $originalFinalPrice;
        }
    }
}
