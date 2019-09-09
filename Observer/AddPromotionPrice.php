<?php

namespace MalibuCommerce\MConnect\Observer;

class AddPromotionPrice implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * PromotionPlugin constructor.
     *
     * @param \Magento\Framework\Registry                                       $registry
     * @param \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection      $priceRuleCollection
     * @param \MalibuCommerce\MConnect\Model\Config                             $config
     * @param \MalibuCommerce\MConnect\Model\Navision\Connection                $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                                          $logger
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->_registry = $registry;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $collection = $observer->getEvent()->getCollection();
        /* @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
        if ($collection->getSize() > 0 ){
            $key = \MalibuCommerce\MConnect\Model\Queue\Promotion::CACHE_TAG;
            $prepareProducts = $this->_registry->registry($key);
            foreach ($collection as $product) {
                $prepareProducts[$product->getSku()] = 1;
            }
            $this->_registry->unregister($key);
            $this->_registry->register($key, $prepareProducts);
        }
        return $this;
    }
}
