<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use MalibuCommerce\MConnect\Model\Config;

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

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    protected function isRetryOnFailureEnabled($websiteId = 0)
    {
        return (bool)$this->config->getWebsiteData(\MalibuCommerce\MConnect\Model\Queue\Promotion::CODE . '/retry_on_failure', $websiteId);
    }

    /**
     * @param int $websiteId
     *
     * @return int
     */
    protected function getRetryAttemptsCount($websiteId = 0)
    {
        return (int)$this->config->getWebsiteData(\MalibuCommerce\MConnect\Model\Queue\Promotion::CODE . '/retry_max_count', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getConnectionTimeout($websiteId = null)
    {
        $timeout = (int)$this->config->getWebsiteData(\MalibuCommerce\MConnect\Model\Queue\Promotion::CODE . '/connection_timeout', $websiteId);
        if ($timeout <= 0) {
            return Config::DEFAULT_NAV_CONNECTION_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getRequestTimeout($websiteId = null)
    {
        $timeout = (int)$this->config->getWebsiteData(\MalibuCommerce\MConnect\Model\Queue\Promotion::CODE . '/request_timeout', $websiteId);
        if ($timeout <= 0) {
            return Config::DEFAULT_NAV_REQUEST_TIMEOUT;
        }

        return $timeout;
    }
}
