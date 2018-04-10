<?php
namespace MalibuCommerce\MConnect\Observer;

class ProcessFrontFinalPriceObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule
    ) {
        $this->logger = $logger;
        $this->rule = $rule;
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
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getProduct();

            $price = $this->rule->matchDiscountPrice($product, $observer->getQty());

            if ($price !== false && (!$product->hasFinalPrice() || $price < $product->getFinalPrice())) {
                $finalPrice = min($product->getData('final_price'), $price);
                $product->setPrice($finalPrice);
                $product->setFinalPrice($finalPrice);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        return $this;
    }
}
