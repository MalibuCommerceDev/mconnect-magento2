<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Promotion extends AbstractModel
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection
     */
    protected $priceRuleCollection;

    /**
     * Promotion constructor.
     *
     * @param \Magento\Framework\Registry                                       $registry
     * @param \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection      $priceRuleCollection
     * @param \MalibuCommerce\MConnect\Model\Config                             $config
     * @param \MalibuCommerce\MConnect\Model\Navision\Connection                $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                                          $logger
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $priceRuleCollection,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->registry = $registry;
        $this->priceRuleCollection = $priceRuleCollection;
        parent::__construct($config, $mConnectNavisionConnection, $logger);

    }


    /**
     * @param int  $page
     * @param bool $lastUpdated
     * @param int  $websiteId
     *
     * @return \simpleXMLElement
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $this->priceRuleCollection->getCustomer();
        $prepareProducts = $this->registry->registry(\MalibuCommerce\MConnect\Model\Queue\Promotion::REGISTRY_KEY_NAV_PROMO_PRODUCTS);
        if (!$prepareProducts) {
            return false;
        }
        $root = new \simpleXMLElement('<promo_export />');

        if (!is_null($this->priceRuleCollection->getCustomer())){
            $root->mag_customer_id = $this->priceRuleCollection->getCustomer()->getId();
            $root->nav_customer_id = $this->priceRuleCollection->getCustomer()->getNavId();
        } else {
            $root->mag_customer_id = '';
            $root->nav_customer_id = '';
        }
        $items = $root->addChild('items');
        foreach ($prepareProducts as $k => $v) {
            $this->addItemChild($items, $k, $v);
            //Always request all items with QTY 1
            if ($v > 1) {
                $this->addItemChild($items, $k, 1);
            }
        }
        return $this->_export('promo_export', $root, $websiteId);
    }

    public function addItemChild($root, $sku, $qty)
    {
        $item = $root->addChild('item');
        $item->addChild('sku', $sku);
        $item->addChild('quantity', $qty);
    }
}