<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;

class Product extends \MalibuCommerce\MConnect\Model\Queue
{
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

    public function importAction()
    {
        $this->initImport();

        $this->setCurrentStore();

        $count       = 0;
        $page        = 0;
        $lastSync    = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME);
        do {
            $result = $this->navProduct->export($page++, $lastUpdated);
            foreach ($result->item as $data) {
                try {
                    $importResult = $this->addProduct($data);
                    if ($importResult) {
                        $count++;
                    }
                    if ($importResult === false) {
                        $this->messages .= 'Unable to import NAV product' . PHP_EOL;
                    }
                } catch (\Exception $e) {
                    $this->messages .= $e->getMessage() . PHP_EOL;
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));
        if ($count > 0) {
            $this->setLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME, $lastSync);
            $this->messages .= PHP_EOL . 'Successfully processed ' . $count . ' NAV records(s).';
        } else {
            $this->messages .= PHP_EOL . 'Nothing to import.';
        }
    }

    public function importSingleAction()
    {
        $this->setCurrentStore();

        $details = json_decode($this->getDetails());
        if (!$details || !isset($details->nav_id) || !$details->nav_id) {
            throw new LocalizedException(__('No NAV ID specified'));
        }
        $result = $this->navProduct->exportSingle($details->nav_id);
        $this->captureEntityId = true;
        $result = $this->addProduct($result->item);
        if ($result === false) {
            throw new LocalizedException(sprintf('Unable to import NAV product "%s"', $details->nav_id));
        }
    }

    public function addProduct($data)
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
            $product = $this->productFactory->create();
        } catch (\Exception $e) {
            $this->messages .= $sku . ': ' . $e->getMessage();

            return false;
        }

        if ($productExists) {
            if (isset($data->item_qty_on_hand)) {
                $stockItem = $product->getExtensionAttributes()->getStockItem();
                if ($stockItem && $stockItem->getId() && $stockItem->getManageStock()) {
                    $stockItem
                        ->setQty((int)$data->item_qty_on_hand)
                        ->setIsInStock((int)(bool)$data->item_qty_on_hand);
                }
            }
        } else {
            $product->setAttributeSetId($this->getDefaultAttributeSetId())
                ->setStoreId($this->storeManager->getStore()->getId())
                ->setTypeId($this->getDefaultTypeId())
                ->setSku($sku)
                ->setVisibility($this->getDefaultVisibility())
                ->setTaxClassId($this->getDefaultTaxClass());

            if (isset($data->item_qty_on_hand)) {
                $product->setStockData(array(
                    'use_config_manage_stock' => 1,
                    'qty'                     => (int)$data->item_qty_on_hand,
                    'is_in_stock'             => (int)(bool)$data->item_qty_on_hand
                ));
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

        if (!isset($data->item_visibility)) {
            $visibilities = ($this->getVisibilityOptions());
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

        if (!empty($data->item_webshop_list)) {
            $ids = (string)$data->item_webshop_list;
            $product->setWebsiteIds(explode(',', $ids));
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
            ->setPrice(number_format((float) $data->item_unit_price, 4, '.', ''))
            ->setStatus((string) $status);

        try {
            if ($product->hasDataChanges() || !empty($this->customAttributesMap)) {
                $this->saveCustomProductAttributes($product, $data);

                $this->productRepository->save($product);

                if (!empty($data->item_webshop_list)) {
                    $this->updateProductWebsites($sku, explode(',', $ids));
                }
                if ($productExists) {
                    $this->messages .= $sku . ': updated';
                } else {
                    $this->messages .= $sku . ': created';
                }
            } else {
                $this->messages .= $sku . ': skipped';
            }

            $this->setEntityId($product->getId());
        } catch (\Exception $e) {

            if ($e instanceof AlreadyExistsException || $e instanceof UrlAlreadyExistsException) {
                $urlKey = $product->formatUrlKey($product->getName() . '-' . $product->getSku());
                $product->setUrlKey($urlKey);

                try {
                    $this->productRepository->save($product);
                    if ($productExists) {
                        $this->messages .= $sku . ': updated';
                    } else {
                        $this->messages .= $sku . ': created';
                    }
                } catch (\Exception $e) {
                    $this->messages .= $sku . ': ' . $e->getMessage();

                    return false;
                }
            } else {
                $this->messages .= $sku . ': ' . $e->getMessage();

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
        $attribute = $product->getResource()->getAttribute($eavAttributeCode);
        if ($attribute->usesSource()) {
            $value = $attribute->getSource()->getOptionId($value);
        }

        return $value;
    }

    public function updateProductWebsites($sku, array $websiteIds)
    {
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
            $id = (int) $id;
            $connection->query('INSERT INTO ' . $tableName . ' (product_id, website_id) VALUES (' . $productId . ', ' . $id . ')');
        }
    }

    public function getDefaultAttributeSetId()
    {
        if (!$this->hasDefaultAttributeSetId()) {
            $this->setDefaultAttributeSetId($this->config->get('product/import_attribute_set'));
        }

        return parent::getDefaultAttributeSetId();
    }

    public function getDefaultTypeId()
    {
        if (!$this->hasDefaultTypeId()) {
            $this->setDefaultTypeId($this->config->get('product/import_type'));
        }

        return parent::getDefaultTypeId();
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

    protected function setCurrentStore()
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
            $this->storeManager->setCurrentStore($this->storeManager->getDefaultStoreView()->getCode());
        } else {
            $this->storeManager->setCurrentStore(\Magento\Store\Model\Store::ADMIN_CODE);
        }
    }
}
