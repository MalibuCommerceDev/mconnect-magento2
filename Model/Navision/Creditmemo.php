<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Creditmemo extends AbstractModel
{
    /**
     * Export Credit Memo to NAV
     *
     * @param \Magento\Sales\Api\Data\CreditmemoInterface $creditMemoEntity
     * @param int                                        $websiteId
     *
     * @return \simpleXMLElement
     */
    public function import(\Magento\Sales\Api\Data\CreditmemoInterface $creditMemoEntity, $websiteId = 0)
    {
        $root = new \simpleXMLElement('<sales_credit_memo_import />');
        $creditMemoObject = $root->addChild('creditMemo');

        $creditMemoObject->mag_order_id = $creditMemoEntity->getIncrementId();
        $creditMemoObject->mag_invoice_id = $creditMemoEntity->getInvoiceId();
        $creditMemoObject->mag_credit_memo_id = $creditMemoEntity->getIncrementId();
        $creditMemoObject->store_id = $creditMemoEntity->getStoreId();
        $creditMemoObject->shipping_amount = number_format((float)$creditMemoEntity->getBaseShippingAmount(), 2, '.', '');
        $creditMemoObject->order_discount_amount = number_format((float)$creditMemoEntity->getBaseDiscountAmount(), 2, '.', '');
        $creditMemoObject->order_tax = number_format((float)$creditMemoEntity->getBaseTaxAmount(), 2, '.', '');
        $this->addItems($creditMemoEntity, $creditMemoObject);

        return $this->_import('sales_credit_memo_import', $root, $websiteId);
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {

    }

    /**
     * Add creditmemo items to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\CreditmemoInterface $creditMemoEntity
     * @param \simpleXMLElement $root
     */
    protected function addItems($creditMemoEntity, &$root)
    {
        foreach ($creditMemoEntity->getAllItems() as $item) {
            $this->addItem($item, $root);
        }
    }

    /**
     * Construct NAV item XML and set item data
     *
     *
     * @param \Magento\Sales\Model\Order\Creditmemo\Item $item
     * @param \simpleXMLElement                     $root
     *
     * @return $this
     */
    protected function addItem($item, &$root)
    {
        $child = $root->addChild('item');
        $child->qty = $item->getQty();
        $child->mag_item_id = $item->getSku();
        $child->order_item_unit_price = $item->getBasePrice();

        return $this;
    }

}