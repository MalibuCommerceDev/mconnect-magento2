<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Promotion extends AbstractModel
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

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
        $this->_registry = $registry;
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
        $prepareProducts = $this->_registry->registry(\MalibuCommerce\MConnect\Model\Queue\Promotion::CACHE_TAG);
        /*if(!$prepareProducts) {
            return false;
        }
        $root = new \simpleXMLElement('<promo_export />');

        if(!is_null($this->priceRuleCollection->getCustomer())){
            $root->mag_customer_id = $this->priceRuleCollection->getCustomer()->getId();
            $root->nav_customer_id = $this->priceRuleCollection->getCustomer()->getNavId();
        } else {
            $root->mag_customer_id = '';
            $root->nav_customer_id = '';
        }
        foreach ($prepareProducts as $k => $v) {
            $item = $root->addChild('item');
            $item->addChild('sku', $k);
            $item->addChild('quantity', $v);
        }
        $item->addChild('sku', '1250');
        $item->addChild('quantity', '1');

        $item->addChild('sku', '1200');
        $item->addChild('quantity', '1');



        $root = simplexml_load_string('<?xml version="1.0" encoding="utf-8"?>
<promo_export>
    <nav_customer_id></nav_customer_id>
    <mag_customer_id></mag_customer_id>
    <item>
        <sku>1150</sku>
        <quantity>1</quantity>
    </item>
    <item>
        <sku>1250</sku>
        <quantity>1</quantity>
    </item>
    <item>
        <sku>1200</sku>
        <quantity>1</quantity>
    </item>
</promo_export>');
        print_r($root); echo '<br/>';
        $t = $this->_export('promo_export', $root, $websiteId);
        print_r($t); die;*/

        $items = '<?xml version="1.0" encoding="utf-8"?>
<promo_export>
    <nav_customer_id>C1110</nav_customer_id>
    <mag_customer_id>67868</mag_customer_id>
    <item>
        <sku>1150</sku>
        <quantity>1</quantity>
        <price>1.22</price>
    </item>
    <item>
        <sku>WT09-XS-Black</sku>
        <quantity>1</quantity>
        <price>12.22</price>
    </item>
</promo_export>';

        return simplexml_load_string($items);
    }

}
