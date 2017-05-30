<?php
namespace MalibuCommerce\MConnect\Model\Queue;


class Product extends \MalibuCommerce\MConnect\Model\Queue
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Product
     */
    protected $mConnectNavisionProduct;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $catalogProduct;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected $catalogResourceModelProductCollection;

    /**
     * @var \Magento\CatalogInventory\Model\Stock\Item
     */
    protected $catalogInventoryStockItem;

    protected $mConnectConfig;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Product $mConnectNavisionProduct,
        \Magento\Catalog\Model\Product $catalogProduct,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $catalogResourceModelProductCollection,
        \Magento\CatalogInventory\Model\Stock\Item $catalogInventoryStockItem,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig
    ) {
        $this->mConnectNavisionProduct = $mConnectNavisionProduct;
        $this->catalogProduct = $catalogProduct;
        $this->catalogResourceModelProductCollection = $catalogResourceModelProductCollection;
        $this->catalogInventoryStockItem = $catalogInventoryStockItem;
        $this->mConnectConfig = $mConnectConfig;
    }
    public function importAction()
    {
        $count       = 0;
        $page        = 0;
        $lastSync    = false;
        $lastUpdated = $this->getLastSync('product');
        do {
            $result = $this->mConnectNavisionProduct->export($page++, $lastUpdated);
            foreach ($result->item as $data) {
                $count++;
                $import = $this->_importProduct($data);
                if ($import === false) {
                    continue;
                }
                $this->_messages .= $import . PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while (isset($result->status->end_of_records) && (string) $result->status->end_of_records === 'false');
        $this->setLastSync('product', $lastSync);
        $this->_messages .= PHP_EOL . 'Processed ' . $count . ' products(s).';
    }

    public function importSingleAction()
    {
        $details = json_decode($this->getDetails());
        if (!$details || !isset($details->nav_id) || !$details->nav_id) {
            throw new \Magento\Framework\Exception\LocalizedException('No nav_id specified');
        }
        $result = $this->mConnectNavisionProduct->exportSingle($details->nav_id);
        $this->_captureEntityId = true;
        $result = $this->_importProduct($result->item);
        if ($result === false) {
            throw new \Magento\Framework\Exception\LocalizedException(sprintf('Unabled to import %s', $details->nav_id));
        }
    }

    protected function _importProduct($data)
    {
        $sku = trim($data->item_nav_id);
        if (empty($sku)) {
            return false;
        }
        $product = $this->catalogProduct;
        $existing = $this->catalogResourceModelProductCollection->addFieldToFilter('sku', $sku)->getFirstItem();
        if ($existing && $existing->getId()) {
            $product->load($existing->getId());
            $stockItem = $this->catalogInventoryStockItem->loadByProduct($product);
            if ($stockItem && $stockItem->getId() && $stockItem->getManageStock()) {
                $stockItem
                    ->setQty($data->item_qty_on_hand)
                    ->setIsInStock((int)(bool) $data->item_qty_on_hand)
                ;
            } else {
                $stockItem = false;
            }
        } else {
            $stockItem = false;
            $product = $this->catalogProduct
                ->setAttributeSetId($this->getDefaultAttributeSetId())
                ->setTypeId($this->getDefaultTypeId())
                ->setSku($sku)
                ->setVisibility($this->getDefaultVisibility())
                ->setTaxClassId($this->getDefaultTaxClass())
                ->setStockData(array(
                    'use_config_manage_stock' => 1,
                    'qty'                     => $data->item_qty_on_hand,
                    'is_in_stock'             => (int)(bool) $data->item_qty_on_hand
                ))
            ;
        }
        $product
            ->setName($data->item_name)
            ->setDescription($data->item_desc)
            ->setWeight($data->item_net_weight)
            ->setPrice($data->item_unit_price)
            ->setStatus($data->item_blocked == 'true' ? \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED : \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        ;
        try {
            $product->save();
            if ($stockItem) {
                $stockItem->save();
            }
            $this->setEntityId($product->getId());
            $this->_messages .= $sku . ': saved';
        } catch (Exception $e) {
            $this->_messages .= $sku . ': ' . $e->getMessage();
        }
    }

    public function getDefaultAttributeSetId()
    {
        if (!$this->hasDefaultAttributeSetId()) {
            $this->setDefaultAttributeSetId($this->mConnectConfig->get('product/import_attribute_set'));
        }
        return parent::getDefaultAttributeSetId();
    }

    public function getDefaultTypeId()
    {
        if (!$this->hasDefaultTypeId()) {
            $this->setDefaultTypeId($this->mConnectConfig->get('product/import_type'));
        }
        return parent::getDefaultTypeId();
    }

    public function getDefaultVisibility()
    {
        if (!$this->hasDefaultVisibility()) {
            $this->setDefaultVisibility($this->mConnectConfig->get('product/import_visibility'));
        }
        return parent::getDefaultVisibility();
    }

    public function getDefaultTaxClass()
    {
        if (!$this->hasDefaultTaxClass()) {
            $this->setDefaultTaxClass($this->mConnectConfig->get('product/import_tax_class'));
        }
        return parent::getDefaultTaxClass();
    }
}
