<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Creditmemo extends AbstractModel
{
    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;

    /**
     * Creditmemo constructor.
     *
     * @param \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory
     * @param \Magento\Customer\Model\CustomerFactory       $customerFactory
     * @param \MalibuCommerce\MConnect\Model\Config         $config
     * @param Connection                                    $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                      $logger
     * @param array                                         $data
     */
    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->customerFactory = $customerFactory;
        $this->orderFactory = $orderFactory;

        parent::__construct($config, $mConnectNavisionConnection, $logger, $data);
    }

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

        $defaultNavId = $this->config->getWebsiteData('customer/default_nav_id_magento_guest', $websiteId);

        /** @var \Magento\Sales\Model\Order $order */
        $orderEntity = $this->orderFactory->create();
        $orderEntity->load($creditMemoEntity->getOrderId());

        $customerDataModel = $this->customerFactory->create()->load($orderEntity->getCustomerId());
        if ($customerDataModel && $customerDataModel->getId()) {
            $defaultNavId = $this->config->getWebsiteData('customer/default_nav_id_magento_registered', $websiteId);
        }

        $creditMemoObject->nav_customer_id = $customerDataModel && !empty($customerDataModel->getNavId())
            ? $customerDataModel->getNavId()
            : $defaultNavId;

        $creditMemoObject->mag_order_id = $orderEntity->getIncrementId();
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
        return false;
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
        $child = $root->addChild('order_item');
        $child->qty = $item->getQty();
        $child->mag_item_id = $item->getSku();
        $child->order_item_unit_price = $item->getBasePrice();

        return $this;
    }
}
