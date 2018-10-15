<?php

namespace MalibuCommerce\MConnect\Plugin;

class ProductPlugin
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
     * Product plugin constructor.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \MalibuCommerce\MConnect\Model\Pricerule $rule
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
    }

    /**
     * Plugin to apply MConnect Price Rules for Product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param $originalFinalPrice
     * @return mixed|null
     */
    public function afterGetPrice(\Magento\Catalog\Model\Product $product, $originalFinalPrice)
    {
        $finalPrice = null;
        try {
            $mconnectPrice = $this->rule->matchDiscountPrice($product, $product->getQty());
            if ($mconnectPrice === false) {

                return $originalFinalPrice;
            }

            if (!$product->hasData('final_price') || $mconnectPrice < $product->getData('final_price')) {
                $finalPrice = $mconnectPrice;
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $originalFinalPrice;
        }

        if (is_null($finalPrice)) {

            return $originalFinalPrice;
        }

        return $finalPrice;
    }
}