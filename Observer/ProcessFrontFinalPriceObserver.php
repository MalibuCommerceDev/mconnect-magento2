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
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getProduct();
            $rule = $this->rule->loadByApplicable($product, $observer->getQty());
            if ($rule && $rule->getId() && (!$product->hasFinalPrice() || $rule->getPrice() < $product->getFinalPrice())) {
                $finalPrice = min($product->getData('final_price'), $rule->getPrice());
                $product->setPrice($finalPrice);
                $product->setFinalPrice($finalPrice);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return $this;
    }
}
