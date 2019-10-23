<?php

namespace MalibuCommerce\MConnect\Observer;

class AggregateCatalogProductTierPriceObserver implements \Magento\Framework\Event\ObserverInterface
{
    const MIN_QTY_TO_SHOW_TIER_PRICE = 1;

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Promotion
     */
    protected $promotion;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Pricerule $rule,
        \MalibuCommerce\MConnect\Model\Queue\Promotion $promotion
    ) {
        $this->rule = $rule;
        $this->promotion = $promotion;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->promotion->getConfig()->isModuleEnabled()) {

            return $this;
        }
        $product = $observer->getEvent()->getProduct();
        $websiteId = $product->getStore()->getWebsiteId();
        if (!$this->promotion->getConfig()->isTierPriceLogicEnabled($websiteId)) {

            return $this;
        }

        $tierPrices = [];
        $mconnectAllPromoPrices = $this->promotion->getAllPromoPrices($product->getSku(), $websiteId);
        if (!empty($mconnectAllPromoPrices)) {
            foreach ((array)$mconnectAllPromoPrices as $itemQty => $itemPrice) {
                if ($itemQty > self::MIN_QTY_TO_SHOW_TIER_PRICE) {
                    $tierPrices[] = [
                        'website_id' => $websiteId,
                        'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                        'price_qty'  => $itemQty,
                        'price'      => $itemPrice
                    ];
                }
            }
        } else {
            /** @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $collection */
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
                        'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
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