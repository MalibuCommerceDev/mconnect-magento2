<?php
namespace MalibuCommerce\MConnect\Model;


class Pricerule extends \Magento\Framework\Model\AbstractModel
{
    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Pricerule');
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param int $qty
     *
     * @return Pricerule
     */
    public function loadByApplicable($product, $qty)
    {
        if ($product instanceof \Magento\Catalog\Model\Product) {
            $sku = $product->getSku();
        } else {
            $sku = $product;
        }
        $qty = max(1, $qty);
        /** @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $collection */
        $collection = $this->getResourceCollection();
        $collection->applyAllFilters($sku, $qty);
        $ruleIds = $collection->getAllIds();
        if (is_array($ruleIds) && sizeof($ruleIds) > 0) {
            $id = current($ruleIds);
            $this->load($id);
        }

        return $this;
    }
}