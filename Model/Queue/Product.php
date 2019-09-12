<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;

class Product extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'product';
    const NAV_XML_NODE_ITEM_NAME = 'item';

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Model\ProductFactory|ProductFactory
     */
    protected $productFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Product|NavProduct
     */
    protected $navProduct;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config|Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;

    /**
     * @var array
     */
    protected $customAttributesMap = [];

    /**
     * Product constructor.
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Catalog\Model\ProductFactory           $productFactory
     * @param \MalibuCommerce\MConnect\Model\Navision\Product $navProduct
     * @param \MalibuCommerce\MConnect\Model\Config           $config
     * @param FlagFactory                                     $queueFlagFactory
     * @param \Magento\Store\Model\StoreManagerInterface      $storeManager
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \MalibuCommerce\MConnect\Model\Navision\Product $navProduct,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->navProduct = $navProduct;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->storeManager = $storeManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->resource = $resource;
    }

    public function initImport()
    {
        return $this;
    }

    public function mapEavToNavCustomProductAttribute($eavAttributeCode, $navAttributeCode)
    {
        $this->customAttributesMap[$eavAttributeCode] = $navAttributeCode;

        return $this->customAttributesMap;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        $this->initImport();

        $this->setCurrentStore($websiteId);

        return $this->processMagentoImport($this->navProduct, $this, $websiteId, $navPageNumber);
    }

    public function importSingleAction($websiteId)
    {
        $this->setCurrentStore($websiteId);

        $details = json_decode($this->getDetails());
        if (!$details || !isset($details->nav_id) || !$details->nav_id) {
            throw new LocalizedException(__('No NAV ID specified'));
        }
        $result = $this->navProduct->exportSingle($details->nav_id, $websiteId);
        $this->captureEntityId = true;
        $result = $this->importEntity($result->item, $websiteId);
        if ($result === false) {
            throw new LocalizedException(sprintf('Unable to import NAV product "%s"', $details->nav_id));
        }
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     */
    public function addProduct($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    protected function getFormattedExceptionString(\Exception $e, $code, $sku)
    {
        $shortTrace = explode("\n", $e->getTraceAsString());
        $shortTrace = count($shortTrace) > 3
            ? $shortTrace[0] . "\n" . $shortTrace[1]
            : implode("\n", $shortTrace);
        return sprintf(
            'SKU "%s": Error [%s] %s' . "\n\n" . '%s' . "...\n",
            $sku,
            $code,
            $e->getMessage(),
            $shortTrace
        );
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        if (empty($data->item_nav_id)) {
            $this->messages .= 'No valid NAV ID found in response XML' . PHP_EOL;

            return false;
        }
        $sku = trim($data->item_nav_id);

        $productExists = false;
        try {
            /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
            $product = $this->productRepository->get($sku, true, null, true);
            $productExists = true;
        } catch (NoSuchEntityException $e) {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productFactory->create();
        } catch (\Exception $e) {
            $this->messages .= $this->getFormattedExceptionString($e, __LINE__, $sku);

            return false;
        }

        if ($productExists) {
            if (isset($data->item_qty_on_hand)) {
                /** @var \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem */
                $stockItem = $product->getExtensionAttributes()->getStockItem();
                if ($stockItem && $stockItem->getId() && $stockItem->getManageStock()) {
                    $stockItem->setQty((int)$data->item_qty_on_hand);

                    $stockStatus = (int)(bool)$data->item_qty_on_hand;
                    if ($stockStatus || $this->getConfig()->isProductOutOfStockStatusMandatory($websiteId)) {
                        $stockItem->setIsInStock($stockStatus);
                    }
                }
            }
            if (!empty($data->item_name) && ($product->getName() != $data->item_name)) {
                $urlKey = $product->formatUrlKey($data->item_name);
                $product->setUrlKey($urlKey);
                $product->setData('save_rewrites_history', true);
            }
        } else {
            $product->setAttributeSetId($this->getDefaultAttributeSetId())
                ->setStoreId($this->storeManager->getStore()->getId())
                ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
                ->setSku($sku)
                ->setVisibility($this->getDefaultVisibility())
                ->setTaxClassId($this->getDefaultTaxClass());

            if (isset($data->item_qty_on_hand)) {
                $stockData = array(
                    'use_config_manage_stock' => 1,
                    'qty'                     => (int)$data->item_qty_on_hand,
                    'is_in_stock'             => 1,
                );

                $stockStatus = (int)(bool)$data->item_qty_on_hand;
                if ($stockStatus || $this->getConfig()->isProductOutOfStockStatusMandatory($websiteId)) {
                    $stockItem['is_in_stock'] = $stockStatus;
                }

                $product->setStockData($stockData);
            }

            if (!empty($data->item_meta_title)) {
                $product->setMetaTitle((string)$data->item_meta_title);
            }

            if (!empty($data->item_meta_desc)) {
                $product->setMetaDescription((string)$data->item_meta_desc);
            }

            if (!empty($data->item_desc)) {
                $product->setDescription((string)$data->item_desc);
            }

            if (!empty($data->item_name)) {
                $product->setName((string)$data->item_name);
            }
        }

        if (isset($data->item_visibility)) {
            $visibilities = $this->getVisibilityOptions();
            $inputVisibility = (int)$data->item_visibility;
            if (array_key_exists($inputVisibility, $visibilities)) {
                $product->setVisibility($inputVisibility);
            } else {
                $visibilities = array_flip($visibilities);
                if (array_key_exists($inputVisibility, $visibilities)) {
                    $product->setVisibility($visibilities[$inputVisibility]);
                }
            }
        }

        if (!empty($data->item_net_weight)) {
            $product->setWeight(number_format((float)$data->item_net_weight, 4, '.', ''));
        }

        $websiteIds = [];
        if (!empty($websiteId)) {
            $websiteIds = [$websiteId];
        }
        if (!empty($data->item_webshop_list)) {
            $websiteIdsString = (string)$data->item_webshop_list;
            $websiteIds = explode('|', $websiteIdsString);
            if (sizeof($websiteIds) < 2) {
                $websiteIds = explode(',', $websiteIdsString);
            }
        }
        if (!empty($websiteIds)) {
            $product->setWebsiteIds($websiteIds);
        }

        $status = $data->item_blocked == 'true'
            ? ProductStatus::STATUS_DISABLED
            : ProductStatus::STATUS_ENABLED;

        /**
         * Set required user defined attributes
         */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_required', '1')
            ->addFilter('is_user_defined', '1')
            ->create();
        $attributes = $this->attributeRepository->getList($searchCriteria)->getItems();

        foreach ($attributes as $attribute) {
            switch ($attribute->getBackendType()) {
                case 'decimal':
                    $value = 0.00;
                    break;
                case 'int':
                    $value = 0;
                    break;
                case 'datetime':
                    $value = '0000-00-00 00:00:00';
                    break;
                default:
                    $value = 'N/A';
                    break;
            }
            $product->setDataUsingMethod($attribute->getAttributeCode(), $value);
        }

        $product
            ->setOptions([])
            ->setPrice(number_format((float)$data->item_unit_price, 4, '.', ''))
            ->setStatus((string)$status);

        try {
            if ($product->hasDataChanges() || !empty($this->customAttributesMap)) {
                $this->saveCustomProductAttributes($product, $data);

                // Fix image roles reset issue
                $product->unsetData('media_gallery');

                $this->productRepository->save($product);

                if (!empty($websiteIds)) {
                    $this->updateProductWebsites($sku, $websiteIds);
                }
                if ($productExists) {
                    $this->messages .= 'SKU ' . $sku . ': updated';
                } else {
                    $this->messages .= 'SKU ' . $sku . ': created';
                }
            } else {
                $this->messages .= 'SKU ' . $sku . ': skipped';
            }

            $this->setEntityId($product->getId());
        } catch (\Exception $e) {

            if ($e instanceof AlreadyExistsException || $e instanceof UrlAlreadyExistsException) {
                $urlKey = $product->formatUrlKey($product->getName() . '-' . $product->getSku());
                $product->setUrlKey($urlKey);

                try {
                    $this->productRepository->save($product);

                    if (!empty($websiteIds)) {
                        $this->updateProductWebsites($sku, $websiteIds);
                    }
                    if ($productExists) {
                        $this->messages .= 'SKU ' . $sku . ': updated';
                    } else {
                        $this->messages .= 'SKU ' . $sku . ': created';
                    }
                } catch (\Exception $e) {
                    $this->messages .= $this->getFormattedExceptionString($e, __LINE__, $sku);

                    return false;
                }
            } else {
                $this->messages .= $this->getFormattedExceptionString($e, __LINE__, $sku);

                return false;
            }
        }
        return true;
    }

    public function saveCustomProductAttributes(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        \simpleXMLElement $data
    ) {
        foreach ($this->customAttributesMap as $eavAttributeCode => $navAttributeCode) {
            if (!isset($data->$navAttributeCode)) {
                continue;
            }

            $value = $this->getCustomProductNavAttributeValue($product, $data, $navAttributeCode, $eavAttributeCode);
            $product->setData($eavAttributeCode, $value);
        }
    }

    public function getCustomProductNavAttributeValue($product, $data, $navAttributeCode, $eavAttributeCode)
    {
        $value = (string)$data->$navAttributeCode;
        // Unset product data when NAV attribute value is not set
        if ($value == '') {
            $value = null;
        }
        $attribute = $product->getResource()->getAttribute($eavAttributeCode);
        if ($attribute && $attribute->usesSource()) {
            $value = $attribute->getSource()->getOptionId($value);
        }

        return $value;
    }

    /**
     * @param string $sku
     * @param array $websiteIds
     *
     * @return bool
     */
    public function updateProductWebsites($sku, array $websiteIds)
    {
        if (empty($websiteIds)) {

            return false;
        }
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('catalog_product_website');
        try {
            /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
            $product = $this->productRepository->get($sku, true, null, true);
        } catch (\Exception $e) {
            return false;
        }

        $productId = $product->getId();
        $connection->query('DELETE FROM ' . $tableName . ' WHERE product_id = ' . $productId);
        foreach ($websiteIds as $id) {
            $id = (int)$id;
            try {
                $connection->query('INSERT INTO ' . $tableName . ' (product_id, website_id) VALUES (' . $productId . ', ' . $id . ')');
            } catch (\Exception $e) {
                // ignore Integrity constraint violation error in Magento 2.3.2
            }
        }

        return true;
    }

    public function getDefaultAttributeSetId()
    {
        if (!$this->hasDefaultAttributeSetId()) {
            $this->setDefaultAttributeSetId($this->config->get('product/import_attribute_set'));
        }

        return parent::getDefaultAttributeSetId();
    }

    public function getDefaultVisibility()
    {
        if (!$this->hasDefaultVisibility()) {
            $this->setDefaultVisibility($this->config->get('product/import_visibility'));
        }

        return parent::getDefaultVisibility();
    }

    /**
     * @return array
     */
    public function getVisibilityOptions()
    {
        return [
            Visibility::VISIBILITY_NOT_VISIBLE => 'Not Visible Individually',
            Visibility::VISIBILITY_IN_CATALOG  => 'Catalog',
            Visibility::VISIBILITY_IN_SEARCH   => 'Search',
            Visibility::VISIBILITY_BOTH        => 'Catalog, Search'
        ];
    }

    public function getDefaultTaxClass()
    {
        if (!$this->hasDefaultTaxClass()) {
            $this->setDefaultTaxClass($this->config->get('product/import_tax_class'));
        }

        return parent::getDefaultTaxClass();
    }

    protected function setCurrentStore($websiteId = 0)
    {
        /**
         * Currently a module-catalog\Model\ProductRepository.php is utilized to add/update product in Magento.
         * But it seems that the store id is retrieved from the current store on save,
         * and not the one set via Product::setStoreId().
         *
         * Thus current store needed to be set to default store in multi-store mode or to admin store in single-store mode
         * before saving product.
         */
        if (!$this->storeManager->hasSingleStore()) {
            $storeIsSet = false;
            if (!empty($websiteId)) {
                $website = $this->storeManager->getWebsite($websiteId);
                if ($website && $website->getId()) {
                    $storeId = $this->storeManager->getGroup($website->getDefaultGroupId())->getDefaultStoreId();
                    $this->storeManager->setCurrentStore($this->storeManager->getStore($storeId)->getCode());
                    $storeIsSet = true;
                }
            }

            if (!$storeIsSet) {
                $this->storeManager->setCurrentStore($this->storeManager->getDefaultStoreView()->getCode());
            }
        } else {
            $this->storeManager->setCurrentStore(\Magento\Store\Model\Store::ADMIN_CODE);
        }
    }
}
