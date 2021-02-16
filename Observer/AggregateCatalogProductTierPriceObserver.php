<?php

namespace MalibuCommerce\MConnect\Observer;

use Magento\Customer\Model\Group;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MalibuCommerce\MConnect\Model\Pricerule;
use MalibuCommerce\MConnect\Model\Queue\Promotion;
use MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection;

class AggregateCatalogProductTierPriceObserver implements ObserverInterface
{
    const MIN_QTY_TO_SHOW_TIER_PRICE = 1;

    /**
     * @var Pricerule
     */
    protected $rule;

    /**
     * @var Promotion
     */
    protected $promotion;

    protected $mconnectConfig;

    public function __construct(
        Pricerule $rule,
        Promotion $promotion
    ) {
        $this->rule = $rule;
        $this->promotion = $promotion;
        $this->mconnectConfig = $this->promotion->getConfig();
    }

    public function execute(Observer $observer)
    {
        if (!$this->mconnectConfig->isModuleEnabled()) {

            return $this;
        }
        $product = $observer->getEvent()->getProduct();
        $websiteId = $product->getStore()->getWebsiteId();
        if (!$this->mconnectConfig->isTierPriceLogicEnabled($websiteId)) {

            return $this;
        }

        $tierPrices = [];
        $prices = $this->promotion->matchPromoPrice($product, null, $websiteId);
        if (is_array($prices)) {
            ksort($prices, SORT_NUMERIC);
        }
        if (!empty($prices)) {
            foreach ((array)$prices as $itemQty => $itemPrice) {
                if ($itemQty > self::MIN_QTY_TO_SHOW_TIER_PRICE) {
                    $tierPrices[] = [
                        'website_id' => $websiteId,
                        'cust_group' => Group::CUST_GROUP_ALL,
                        'price_qty'  => $itemQty,
                        'price'      => $itemPrice
                    ];
                }
            }
        } else {
            /** @var Collection $collection */
            $collection = $this->rule->getResourceCollection();
            $collection
                ->applySkuFilter($product->getSku())
                ->applyWebsiteFilter($websiteId)
                ->applyCustomerFilter()
                ->applyFromToDateFilter()
                ->setOrder('price', \Zend_Db_Select::SQL_ASC)
                ->addFieldToFilter('qty_min', [['from' => self::MIN_QTY_TO_SHOW_TIER_PRICE]]);

            if ($collection->getSize() > 0) {
                foreach ($collection as $item) {
                    $tierPrices[] = [
                        'website_id' => $websiteId,
                        'cust_group' => Group::CUST_GROUP_ALL,
                        'price_qty'  => $item->getData('qty_min'),
                        'price'      => $item->getData('price')
                    ];
                }
            }
        }

        if (!empty($tierPrices)) {
            $product->setTierPrice($tierPrices);
        }

        return $this;
    }
}
