<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml\System\Config\Product;


class Visibility
{

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $catalogProductVisibility;

    public function __construct(
        \Magento\Catalog\Model\Product\Visibility $catalogProductVisibility
    ) {
        $this->catalogProductVisibility = $catalogProductVisibility;
    }
    public function toOptionArray()
    {
        return $this->catalogProductVisibility->getOptionArray();
    }
}
