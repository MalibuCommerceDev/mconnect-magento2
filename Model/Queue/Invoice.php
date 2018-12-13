<?php

namespace MalibuCommerce\MConnect\Model\Queue;

class Invoice extends \MalibuCommerce\MConnect\Model\Queue
{
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

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Invoice $navInvoice,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory
    ) {
        $this->navInvoice = $navInvoice;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
    }

    public function importAction($websiteId)
    {
        $page = $count = 0;
        $detectedErrors = $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_INVOICE_SYNC_TIME, $websiteId);
        do {
            $result = $this->navInvoice->export($page++, $lastUpdated, $websiteId);
            foreach ($result->invoice as $data) {
                try {
                    $importResult = $this->importInvoice($data, $websiteId);
                    if ($importResult) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    $detectedErrors = true;
                    $this->messages .= $e->getMessage() . PHP_EOL;
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));

        if (!$detectedErrors || $this->config->getWebsiteData('invoice/ignore_magento_errors', $websiteId)) {
            $this->setLastSyncTime(Flag::FLAG_CODE_LAST_INVOICE_SYNC_TIME, $lastSync, $websiteId);
        }

        if ($count > 0) {
            $this->messages .= PHP_EOL . 'Successfully processed ' . $count . ' NAV records(s).';
        } else {
            $this->messages .= PHP_EOL . 'Nothing to import.';
        }
    }

    /**
     * Generate Magento Invoice for specified order
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $qtys
     *
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \LogicException
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

        $invoice->setRequestedCaptureCase($this->config->get('invoice/capture_type'));
        $invoice->register();

        return $invoice;
    }

    /**
     * Import invoice from NAV to Magento
     *
     * @param \SimpleXMLElement $entity
     * @param int $websiteId
     *
     * @return bool
     * @throws \Throwable
     */
    protected function importInvoice(\SimpleXMLElement $entity, $websiteId)
    {
        if ($this->config->get('shipment/create_invoice_with_shipment')) {

            return true;
        }

        $incrementId = (string)$entity->mag_order_id;
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
                if ($this->config->get('invoice/send_email_enabled')) {
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