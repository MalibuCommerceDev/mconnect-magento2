<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml\System\Config\Product\Attribute;

class Set
{

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection
     */
    protected $eavResourceModelEntityAttributeSetCollection;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $catalogProduct;

    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\Collection $eavResourceModelEntityAttributeSetCollection,
        \Magento\Catalog\Model\Product $catalogProduct
    ) {
        $this->eavResourceModelEntityAttributeSetCollection = $eavResourceModelEntityAttributeSetCollection;
        $this->catalogProduct = $catalogProduct;
    }
    public function toOptionArray()
    {
        return $this->eavResourceModelEntityAttributeSetCollection
            ->setEntityTypeFilter($this->catalogProduct->getResource()->getEntityType()->getId())
            ->load()
            ->toOptionArray();
    }
}
