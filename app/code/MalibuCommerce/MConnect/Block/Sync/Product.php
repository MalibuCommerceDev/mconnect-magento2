<?php
namespace MalibuCommerce\MConnect\Block\Sync;


class Product extends \MalibuCommerce\MConnect\Block\Sync
{

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $catalogProduct;

    public function __construct(
        \Magento\Catalog\Model\Product $catalogProduct
    ) {
        $this->catalogProduct = $catalogProduct;
    }
    public function getTitle()
    {
        return $this->__('Sync Product: %s', $this->getIdentifier());
    }

    public function getProduct()
    {
        if (!$this->hasProduct()) {
            $sku = $this->getIdentifier();
            if ($sku) {
                $product = $this->catalogProduct;
                $id = $product->getIdBySku($sku);
                if ($id) {
                    $product->load($id);
                    if (!$product || !$product->getId()) {
                        $product = false;
                    }
                }
            } else {
                $product = false;
            }
            $this->setProduct($product);
        }
        return parent::getProduct();
    }
}
