<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
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
     * Product constructor.
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Catalog\Model\ProductFactory           $productFactory
     * @param \MalibuCommerce\MConnect\Model\Navision\Product $navProduct
     * @param \MalibuCommerce\MConnect\Model\Config           $config
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \MalibuCommerce\MConnect\Model\Navision\Product $navProduct,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory
    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->navProduct = $navProduct;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
    }

    public function importAction()
    {
        $count       = 0;
        $page        = 0;
        $lastSync    = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME);
        $result = false;
        do {
            try {
                $result = $this->navProduct->export($page++, $lastUpdated);
                foreach ($result->item as $data) {
                    $count++;
                    $import = $this->_importProduct($data);
                    $this->messages .= PHP_EOL;
                }
                if (!$lastSync) {
                    $lastSync = $result->status->current_date_time;
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage();
            }
        } while ($result && isset($result->status->end_of_records) && (string) $result->status->end_of_records === 'false');
        $this->setLastSyncTime(Flag::FLAG_CODE_LAST_PRODUCT_SYNC_TIME, $lastSync);
        $this->messages .= PHP_EOL . 'Processed ' . $count . ' products(s).';
    }

    public function importSingleAction()
    {
        $details = json_decode($this->getDetails());
        if (!$details || !isset($details->nav_id) || !$details->nav_id) {
            throw new LocalizedException(__('No nav_id specified'));
        }
        $result = $this->navProduct->exportSingle($details->nav_id);
        $this->captureEntityId = true;
        $result = $this->_importProduct($result->item);
        if ($result === false) {
            throw new LocalizedException(sprintf('Unabled to import %s', $details->nav_id));
        }
    }

    protected function _importProduct($data)
    {
        $sku = trim($data->item_nav_id);
        if (empty($sku)) {
            return false;
        }

        $productExists = false;
        try {
            $product = $this->productRepository->get($sku, true, null, true);
            $productExists = true;
        } catch (NoSuchEntityException $e) {
            $product = $this->productFactory->create();
        } catch (\Exception $e) {
            $this->messages .= $sku . ': ' . $e->getMessage();
            return false;
        }

        if ($productExists) {
            /** @var ProductExtensionInterface $ea */
            $stockItem = $product->getExtensionAttributes()->getStockItem();

            if ($stockItem && $stockItem->getId() && $stockItem->getManageStock()) {
                $stockItem
                    ->setQty($data->item_qty_on_hand)
                    ->setIsInStock((int)(bool) $data->item_qty_on_hand);
            }
        } else {
            $product->setAttributeSetId($this->getDefaultAttributeSetId())
                ->setStoreId(\Magento\Store\Model\Store::DISTRO_STORE_ID)
                ->setTypeId($this->getDefaultTypeId())
                ->setSku($sku)
                ->setVisibility($this->getDefaultVisibility())
                ->setTaxClassId($this->getDefaultTaxClass())
                ->setStockData(array(
                    'use_config_manage_stock' => 1,
                    'qty'                     => $data->item_qty_on_hand,
                    'is_in_stock'             => (int)(bool) $data->item_qty_on_hand
                ));
        }

        if (!empty($data->item_meta_title)) {
            $product->setMetaTitle((string) $data->item_meta_title);
        }

        if (!empty($data->item_meta_desc)) {
            $product->setMetaDescription((string) $data->item_meta_desc);
        }

        if (!empty($data->item_net_weight)) {
            $product->setWeight(number_format((float) $data->item_net_weight, 4, '.', ''));
        }

        if (!empty($data->item_desc)) {
            $product->setDescription((string) $data->item_desc);
        }

        $status = $data->item_blocked == 'true'
            ? ProductStatus::STATUS_DISABLED
            : ProductStatus::STATUS_ENABLED;

        $product
            ->setOptions([])
            ->setName((string) $data->item_name)
            ->setPrice(number_format((float) $data->item_unit_price, 4, '.', ''))
            ->setStatus((string) $status);

        try {
            if ($product->hasDataChanges()) {
                $this->productRepository->save($product);
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
                }  catch (\Exception $e) {
                    $this->messages .= $sku . ': ' . $e->getMessage();
                }
            } else {
                $this->messages .= $sku . ': ' . $e->getMessage();
            }
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

    public function getDefaultTaxClass()
    {
        if (!$this->hasDefaultTaxClass()) {
            $this->setDefaultTaxClass($this->config->get('product/import_tax_class'));
        }
        return parent::getDefaultTaxClass();
    }
}
