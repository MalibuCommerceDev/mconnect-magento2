<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use MalibuCommerce\MConnect\Helper\Customer;
use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Model\Queue\Promotion as PromotionModel;

class Promotion extends AbstractModel
{
    /** @var Customer */
    protected $customerHelper;

    /** @var array */
    protected $requestedProducts = [];

    /**
     * Promotion constructor.
     *
     * @param Customer                 $customerHelper
     * @param Config                   $config
     * @param Connection               $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Customer $customerHelper,
        Config $config,
        Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->customerHelper = $customerHelper;
        parent::__construct($config, $mConnectNavisionConnection, $logger);
    }

    /**
     * @param int  $page
     * @param bool $lastUpdated
     * @param int  $websiteId
     *
     * @return \simpleXMLElement
     * @throws \Throwable
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        if (empty($this->getRequestedProducts())) {

            $nodeName = PromotionModel::NAV_XML_NODE_ITEM_NAME;
            new \simpleXMLElement('<' . $nodeName . ' />');
        }
        $root = new \simpleXMLElement('<promo_export />');
        $root->mag_customer_id = '';
        $root->nav_customer_id = '';

        $customer = $this->customerHelper->getCurrentCustomer();
        if (!is_null($customer)) {
            $root->mag_customer_id = $customer->getId();
            $root->nav_customer_id = $customer->getNavId();
        }
        $items = $root->addChild('items');

        foreach ($this->getRequestedProducts() as $k => $v) {
            $this->addSkuQtyToRequestPayload($items, $k, $v);
            // Always request all items with QTY 1
            if ($v > 1) {
                $this->addSkuQtyToRequestPayload($items, $k, 1);
            }
        }

        $this->requestedProducts = [];
        return $this->_export('promo_export', $root, $websiteId);
    }

    /**
     * @param \simpleXMLElement $root
     * @param string $sku
     * @param int $qty
     */
    protected function addSkuQtyToRequestPayload($root, $sku, $qty)
    {
        $item = $root->addChild('item');
        $item->addChild('sku', $sku);
        $item->addChild('quantity', $qty);
    }

    /**
     * @param array $productsSkuToQtyMap
     */
    public function setRequestedProducts(array $productsSkuToQtyMap)
    {
        $this->requestedProducts = $productsSkuToQtyMap;
    }

    /**
     * @return array
     */
    public function getRequestedProducts()
    {
        return $this->requestedProducts;
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    protected function isRetryOnFailureEnabled($websiteId = 0)
    {
        return (bool)$this->config->getWebsiteData(PromotionModel::CODE . '/retry_on_failure', $websiteId);
    }

    /**
     * @param int $websiteId
     *
     * @return int
     */
    protected function getRetryAttemptsCount($websiteId = 0)
    {
        return (int)$this->config->getWebsiteData(PromotionModel::CODE . '/retry_max_count', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getConnectionTimeout($websiteId = null)
    {
        $timeout = (int)$this->config->getWebsiteData(PromotionModel::CODE . '/connection_timeout', $websiteId);
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
        $timeout = (int)$this->config->getWebsiteData(PromotionModel::CODE . '/request_timeout', $websiteId);
        if ($timeout <= 0) {
            return Config::DEFAULT_NAV_REQUEST_TIMEOUT;
        }

        return $timeout;
    }
}
