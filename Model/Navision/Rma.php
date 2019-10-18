<?php

namespace MalibuCommerce\MConnect\Model\Navision;


class Rma extends AbstractModel
{
    /**
     * Export order to NAV (or if from NAV side - this is actually an order import from Magento)
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param int $websiteId
     *
     * @return \simpleXMLElement
     */
    public function import(\Magento\Rma\Model\Rma $rmaEntity, $websiteId = 0)
    {
        $root = new \simpleXMLElement('<sale_rma_import />');
        $orderObject = $root->addChild('RMA');
        $orderObject->order_id       = $rmaEntity->getOrderId();
        $this->addItems($rmaEntity, $orderObject);

        return $this->_import('rma_import', $root, $websiteId);
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {

    }

    /**
     * Add rma items to NAV payload XML
     *
     * @param \Magento\Rma\Model\Rma
     * @param \simpleXMLElement $root
     */
    protected function addItems($rmaEntity, &$root)
    {
        foreach ($rmaEntity->getItems() as $item) {
            $this->addItem($item, $root);
        }
    }

    /**
     * Construct NAV item XML and set item data
     *
     *
     * @param \Magento\Rma\Model\Rma $item
     * @param \simpleXMLElement $root
     *
     * @return $this
     */
    protected function addItem($item, &$root)
    {
        $child = $root->addChild('item');
        $child->qty = $item->getQtyRequested();
        $child->resolution = $this->getAttributeLabel($item->getResolution());
        $child->condition  = $this->getAttributeLabel($item->getCondition());
        $child->reason     = $this->getAttributeLabel($item->getReason());


        return $this;
    }

    /**
     * Return option Value
     *
     * @param int $optionValue
     *
     * @return string
     */
    public function getAttributeLabel($optionValue)
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $attributeInfo = $objectManager->create('\Magento\Eav\Api\Data\AttributeOptionInterfaceFactory')->create()
            ->load($optionValue);
        $attributeId = $attributeInfo->getAttributeId();
        $optionData = $objectManager->create('\Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection')
            ->setPositionOrder('asc')
            ->setAttributeFilter($attributeId)
            ->setIdFilter($optionValue)
            ->setStoreFilter()
            ->load();
        $option = $optionData->getFirstItem();

        return $option->getValue();
    }
}