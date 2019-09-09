<?php
namespace MalibuCommerce\MConnect\Observer;

/**
 * Override product price on PDP, Cart, Checkout level both in admin and on frontend
 */
class ProcessFinalPriceObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Promotion
     */
    protected $promotion;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule,
        \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
        $this->config = $config;
        $this->promotion = $promotion;
    }

    /**
     * Apply MConnect product price rule to Product's final price
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $finalPrice = null;
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getProduct();
            $promoPrice = $this->promotion->getPromoPrice($product, $observer->getQty());
            if ($promoPrice != false)  {
                $finalPrice = $promoPrice;
            } else {
                $mconnectPrice = $this->rule->matchDiscountPrice($product, $observer->getQty(), $product->getStore()->getWebsiteId());

                if ($mconnectPrice === false) {

                    return $this;
                }

                if (!$product->hasData('final_price') || $mconnectPrice <= $product->getData('final_price')) {
                    $finalPrice = $mconnectPrice;
                }
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
