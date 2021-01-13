<?php

namespace MalibuCommerce\MConnect\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MalibuCommerce\MConnect\Model\Pricerule;
use MalibuCommerce\MConnect\Model\Queue\Promotion;

/**
 * Override product price on PDP, Cart, Checkout level both in admin and on frontend
 */
class ProcessFinalPriceObserver implements ObserverInterface
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
     * Apply MConnect product price rule to Product's final price
     *
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        if (!$this->promotion->getConfig()->isModuleEnabled()) {

            return $this;
        }

        $finalPrice = null;
        try {
            /** @var Product $product */
            $product = $observer->getProduct();
            $websiteId = $product->getStore()->getWebsiteId();
            $qty = $observer->getQty();
            $qty = max(1, $qty);

            $mconnectPrice = $this->promotion->matchPromoPrice($product, $qty, $websiteId);

            if ($mconnectPrice === false) {
                $mconnectPrice = $this->rule->matchDiscountPrice($product, $qty, $websiteId);
            }

            if ($mconnectPrice === false) {

                return $this;
            }

            if (!$product->hasData('final_price') || $mconnectPrice <= $product->getData('final_price')) {
                $finalPrice = $mconnectPrice;
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        if ($finalPrice === null) {

            return $this;
        } else {
            $product->setPrice($finalPrice);
            $product->setFinalPrice($finalPrice);
        }

        return $this;
    }
}
