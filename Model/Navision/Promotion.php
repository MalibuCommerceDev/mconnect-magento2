<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Promotion extends AbstractModel
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection
     */
    protected $priceRuleCollection;

    /**
     * Promotion constructor.
     *
     * @param \Magento\Framework\Registry                                  $registry
     * @param \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection $priceRuleCollection
     * @param \MalibuCommerce\MConnect\Model\Config                        $config
     * @param \MalibuCommerce\MConnect\Model\Navision\Connection           $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                                     $logger
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection $priceRuleCollection,
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
     * @return bool|\simpleXMLElement
     * @throws \Throwable
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $prepareProducts = $this->registry->registry(
            \MalibuCommerce\MConnect\Model\Queue\Promotion::REGISTRY_KEY_NAV_PROMO_PRODUCTS
        );
        if (!$prepareProducts) {

            return false;
        }

        $root = new \simpleXMLElement('<promo_export />');
        $root->mag_customer_id = '';
        $root->nav_customer_id = '';

        $customer = $this->priceRuleCollection->getCustomer();
        if (!is_null($customer)) {
            $root->mag_customer_id = $customer->getId();
            $root->nav_customer_id = $customer->getNavId();
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

    /**
     * @param \simpleXMLElement $root
     * @param string $sku
     * @param int $qty
     */
    public function addItemChild($root, $sku, $qty)
    {
        $item = $root->addChild('item');
        $item->addChild('sku', $sku);
        $item->addChild('quantity', $qty);
    }
}
