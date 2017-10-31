<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\LocalizedException;

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
     * @var \MalibuCommerce\MConnect\Model\Config|Config
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

    public function importAction()
    {
        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_INVOICE_SYNC_TIME);
        do {
            $result = $this->navInvoice->export($page++, $lastUpdated);
            foreach ($result->invoice as $data) {
                $count++;
                try {
                    $import = $this->importInvoice($data);
                } catch (\Exception $e) {
                    $this->messages .= $e->getMessage();
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));
        $this->setLastSyncTime(Flag::FLAG_CODE_LAST_INVOICE_SYNC_TIME, $lastSync);
        $this->messages .= PHP_EOL . 'Processed ' . $count . ' invoice(s).';
    }

    protected function importInvoice(\SimpleXMLElement $entity)
    {
        $incrementId = (string)$entity->mag_order_id;
        $order = $this->getOrder($incrementId);

        try {
            if (!$order || !$order->getId()) {
                throw new LocalizedException(__('The order #%1 no longer exists.', $incrementId));
            }

            if (!$order->canInvoice()) {
                throw new LocalizedException(
                    __('The order #%1 does not allow an invoice to be created.', $incrementId)
                );
            }

            $invoice = $this->invoiceService->prepareInvoice($order);

            if (!$invoice) {
                throw new LocalizedException(__('Can\'t save the invoice for order #%1 right now.', $incrementId));
            }

            if (!$invoice->getTotalQty()) {
                throw new LocalizedException(
                    __('Can\'t create an invoice without products for order #%1.', $incrementId)
                );
            }

            //@todo capture_type
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();

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
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage();
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }

        return true;
    }

    protected function getOrder($incrementId)
    {
        try {
            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order && $order->getId()) {

                return $order;
            }
        } catch (\Exception $e) {
            $this->messages .= $email . ': ' . $e->getMessage();
        }

        return false;
    }
}