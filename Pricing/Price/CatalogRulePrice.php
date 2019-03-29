<?php

namespace MalibuCommerce\MConnect\Pricing\Price;

use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Adjustment\Calculator;
use Magento\Framework\Pricing\Price\AbstractPrice;
use Magento\Framework\Pricing\Price\BasePriceProviderInterface;

/**
 * Class CatalogRulePrice
 */
class CatalogRulePrice extends AbstractPrice implements BasePriceProviderInterface
{
    /**
     * Price type identifier string
     */
    const PRICE_CODE = 'mconnect_rule_price';

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        Product $saleableItem,
        $quantity,
        Calculator $calculator,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Psr\Log\LoggerInterface $logger,
        \MalibuCommerce\MConnect\Model\Pricerule $rule
    ) {
        parent::__construct($saleableItem, $quantity, $calculator, $priceCurrency);
        $this->logger = $logger;
        $this->rule = $rule;
    }

    /**
     * Returns catalog rule value
     *
     * @return float|boolean
     */
    public function getValue()
    {
        if (null === $this->value) {
            if ($this->product->hasData(self::PRICE_CODE)) {
                $this->value = floatval($this->product->getData(self::PRICE_CODE)) ?: false;
            } else {
                try {
                    $this->value = $this->rule->matchDiscountPrice(
                        $this->product,
                        $this->product->getQty(),
                        $this->product->getStore()->getWebsiteId()
                    );
                } catch (\Throwable $e) {
                    $this->logger->critical($e);
                }

                $this->value = $this->value ? floatval($this->value) : false;
            }
            if ($this->value) {
                $this->value = $this->priceCurrency->convertAndRound($this->value);
            }
        }

        return $this->value;
    }
}
