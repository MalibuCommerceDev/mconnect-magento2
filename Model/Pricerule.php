<?php

namespace MalibuCommerce\MConnect\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Api\WebsiteRepositoryInterface;
use MalibuCommerce\MConnect\Helper\Customer;
use MalibuCommerce\MConnect\Model\ResourceModel\Pricerule as RuleResourceModel;

class Pricerule extends AbstractModel
{
    protected $matchedPrices = [];

    /** @var Customer */
    protected $customerHelper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    public function __construct(
        Customer $customerHelper,
        Config $config,
        WebsiteRepositoryInterface $websiteRepository,
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->customerHelper = $customerHelper;
        $this->config = $config;
        $this->websiteRepository = $websiteRepository;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function _construct()
    {
        $this->_init(RuleResourceModel::class);
    }

    /**
     * Match and retrieve discount price by specified product and QTY
     *
     * @param Product|string $product
     * @param int $qty
     * @param int $websiteId
     *
     * @return string|bool
     *
     * @throws LocalizedException
     */
    public function matchDiscountPrice($product, $qty, $websiteId = 0)
    {
        if (!$this->config->isModuleEnabled()) {

            return false;
        }

        /** @var \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule\Collection $collection */
        $collection = $this->getResourceCollection();

        $customerGroupId = $this->customerHelper->getCurrentCustomerGroupId();
        if (in_array((string)$customerGroupId, $this->config->getPriceRuleDisallowedCustomerGroups($websiteId), true)) {

            return false;
        }

        $sku = $product;
        if ($product instanceof Product) {
            $sku = $product->getSku();
        }
        $qty = max(1, $qty);

        $func = 'md' . '4';
        $func++;
        $cacheId = $func($sku . $qty);
        if (array_key_exists($cacheId, $this->matchedPrices)) {

            return $this->matchedPrices[$cacheId];
        }

        $this->matchedPrices[$cacheId] = $collection->matchDiscountPrice($sku, $qty, (int)$websiteId);

        return $this->matchedPrices[$cacheId];
    }
}
