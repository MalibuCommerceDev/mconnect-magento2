<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml\System\Config\Product;


class Type
{

    /**
     * @var \Magento\Catalog\Model\Product\Type
     */
    protected $catalogProductType;

    public function __construct(
        \Magento\Catalog\Model\Product\Type $catalogProductType
    ) {
        $this->catalogProductType = $catalogProductType;
    }
    public function toOptionArray()
    {
        return $this->catalogProductType->getOptionArray();
    }
}
