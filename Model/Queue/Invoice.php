<?php

namespace MalibuCommerce\MConnect\Model\Queue;

class Invoice extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'invoice';
    const NAV_XML_NODE_ITEM_NAME = 'invoice';

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Invoice
     */
    protected $navInvoice;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Invoice $navInvoice,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->navInvoice = $navInvoice;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int $websiteId
     * @param int $navPageNumber
     *
     * @return bool|\Magento\Framework\DataObject|Invoice
     * @throws \Exception
     */
    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navInvoice, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     *
     * @throws \Throwable
     */
    public function importInvoice($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        if ($this->config->get('shipment/create_invoice_with_shipment')) {

            return true;
        }

        $incrementId = (string)$data->mag_order_id;
        $order = $this->getOrder($incrementId);

        try {
            if (!$order || !$order->getId()) {
                throw new \LogicException(__('The order #%1 no longer exists.', $incrementId));
            }

            $invoice = $this->createInvoice($order);

            $invoice->getOrder()->setIsInProcess(true);
            $invoice->getOrder()->setSkipMconnect(true);

            $saveTransaction = $this->transactionFactory->create();
            $saveTransaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $saveTransaction->save();

            $this->messages .= 'Order #' . $incrementId . ' invoiced, invoice #' . $invoice->getIncrementId();

            // send invoice email
            try {
                if ($this->config->get($this->getQueueCode() . '/send_email_enabled')) {
                    $this->invoiceSender->send($invoice);
                }
            } catch (\Throwable $e) {
                $this->messages .= $e->getMessage();
            }

            return true;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Generate Magento Invoice for specified order
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $qtys
     *
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createInvoice($order, $qtys = [])
    {
        $orderIncrementId = $order->getIncrementId();
        if (!$order->canInvoice()) {
            throw new \LogicException(
                __('The order #%1 does not allow an invoice to be created.', $orderIncrementId)
            );
        }

        $invoice = $this->invoiceService->prepareInvoice($order, $qtys);

        if (!$invoice) {
            throw new \LogicException(__('Can\'t save the invoice for order #%1 right now.', $orderIncrementId));
        }

        if (!$invoice->getTotalQty()) {
            throw new \LogicException(
                __('Can\'t create an invoice without products for order #%1.', $orderIncrementId)
            );
        }

        $paymentMethod = $order->getPayment()->getMethod();
        $invoiceFlag = false;
        $websiteId = $this->storeManager->getStore($order->getStoreId())->getWebsiteId();
        $getEnabledPaymentMEthodForDoNoCapture = (string)$this->config->getWebsiteData($this->getQueueCode() . '/invoice_do_not_capture', $websiteId);
        $getEnabledPaymentMEthodForOfflineCapture = (string)$this->config->getWebsiteData($this->getQueueCode() . '/invoice_offline_capture', $websiteId);
        $getEnabledPaymentMEthodForOnlineCapture = (string)$this->config->getWebsiteData($this->getQueueCode() . '/invoice_online_capture', $websiteId);

        if (is_array(explode(',', $getEnabledPaymentMEthodForDoNoCapture))) {
            if (in_array($paymentMethod, explode(',', $getEnabledPaymentMEthodForDoNoCapture))) {
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
                $invoiceFlag = true;
            }
        }

        if (is_array(explode(',', $getEnabledPaymentMEthodForOfflineCapture)) && !$invoiceFlag) {
            if (in_array($paymentMethod, explode(',', $getEnabledPaymentMEthodForOfflineCapture))) {
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoiceFlag = true;
            }
        }

        if (is_array(explode(',', $getEnabledPaymentMEthodForOnlineCapture)) && !$invoiceFlag) {
            if (in_array($paymentMethod, explode(',', $getEnabledPaymentMEthodForOnlineCapture))) {
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            }
        }

        $invoice->register();

        return $invoice;
    }

    /**
     * Load Magento Order by Increment ID
     *
     * @param string $incrementId
     *
     * @return bool|\Magento\Sales\Model\Order
     */
    protected function getOrder($incrementId)
    {
        try {
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order && $order->getId()) {

                return $order;
            }
        } catch (\Throwable $e) {
            $this->messages .= 'Cannot load order #' . $incrementId . ': ' . $e->getMessage();
        }

        return false;
    }
}
