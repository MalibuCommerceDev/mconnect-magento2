<?php

namespace MalibuCommerce\MConnect\Model\Navision;


class Creditmemo extends AbstractModel
{

    /**
     * Export order to NAV (or if from NAV side - this is actually an order import from Magento)
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param int $websiteId
     *
     * @return \simpleXMLElement
     */
    public function import(\Magento\Sales\Model\Order\Creditmemo $creditMemoEntity, $websiteId = 0)
    {
        $root = new \simpleXMLElement('<sale_creditMemo_import />');
        $orderObject = $root->addChild('creditMemo');

        $orderObject->mag_order_id       = $creditMemoEntity->getOrderId();
        $orderObject->mag_invoice_id     = $creditMemoEntity->getInvoiceId();
        $orderObject->mag_credit_memo_id = $creditMemoEntity->getId();

        $this->addItems($creditMemoEntity, $orderObject);

        return $this->_import('order_import', $root, $websiteId);
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {

    }

    /**
     * Add creditmemo items to NAV payload XML
     *
     * @param \Magento\Sales\Model\Order\Creditmemo
     * @param \simpleXMLElement $root
     */
    protected function addItems($creditMemoEntity, &$root)
    {
        foreach ($creditMemoEntity->getItems() as $item) {
            $this->addItem($item, $root);
        }
    }

    /**
     * Construct NAV item XML and set item data
     *
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $item
     * @param \simpleXMLElement $root
     *
     * @return $this
     */
    protected function addItem($item, &$root)
    {
        $child = $root->addChild('item');
        $child->qty = $item->getQty();
        $child->mag_item_id = $item->getSku();
        $child->order_item_unit_price = ($item->getParentItem() && ($item->getParentItem()->getProductType() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE))
            ? $item->getParentItem()->getBasePrice()
            : $item->getBasePrice();

        return $this;
    }


}