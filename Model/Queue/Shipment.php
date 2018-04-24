<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;

class Shipment extends \MalibuCommerce\MConnect\Model\Queue
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Shipment
     */
    protected $navShipment;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $shipmentFactory;

    /**
     * @var \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface
     */
    protected $shipmentValidator;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\ShipmentSender
     */
    protected $shipmentSender;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Invoice
     */
    protected $malibuInvoice;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Shipping\Model\Config
     */
    protected $shippingConfig;

    /**
     * Shipment constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\Navision\Shipment               $navShipment
     * @param \Magento\Sales\Model\OrderFactory                              $orderFactory
     * @param \Magento\Sales\Model\Order\ShipmentFactory                     $shipmentFactory
     * @param \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface $shipmentValidator
     * @param \Magento\Framework\DB\TransactionFactory                       $transactionFactory
     * @param \Magento\Sales\Model\Order\Email\Sender\ShipmentSender         $shipmentSender
     * @param \MalibuCommerce\MConnect\Model\Config                          $config
     * @param FlagFactory                                                    $queueFlagFactory
     * @param Invoice                                                        $malibuInvoice
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender          $invoiceSender
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Shipment $navShipment,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface $shipmentValidator,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Queue\Invoice $malibuInvoice,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Shipping\Model\Config $shippingConfig
    ) {
        $this->navShipment = $navShipment;
        $this->orderFactory = $orderFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentValidator = $shipmentValidator;
        $this->transactionFactory = $transactionFactory;
        $this->shipmentSender = $shipmentSender;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->malibuInvoice = $malibuInvoice;
        $this->invoiceSender = $invoiceSender;
        $this->shippingConfig = $shippingConfig;
    }

    public function importAction()
    {
        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_SHIPMENT_SYNC_TIME);
        do {
            $result = $this->navShipment->export($page++, $lastUpdated);
            foreach ($result->shipment as $data) {
                try {
                    $importResult = $this->importShipment($data);
                    if ($importResult) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->messages .= $e->getMessage() . PHP_EOL;
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));
        if ($count > 0) {
            $this->setLastSyncTime(Flag::FLAG_CODE_LAST_SHIPMENT_SYNC_TIME, $lastSync);
            $this->messages .= PHP_EOL . 'Successfully processed ' . $count . ' NAV records(s).';
        } else {
            $this->messages .= PHP_EOL . 'Nothing to import.';
        }
    }

    protected function importShipment(\SimpleXMLElement $entity)
    {
        $incrementId = (string)$entity->mag_order_id;
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder($incrementId);

        try {
            if (!$order || !$order->getId()) {
                throw new LocalizedException(__('The order #%1 no longer exists.', $incrementId));
            }

            if (!$order->canShip()) {
                throw new LocalizedException(
                    __('The order #%1 does not allow a shipment to be created.', $incrementId)
                );
            }

            $navShipmentItems = array();
            foreach ($entity->shipment_item as $item) {
                $navShipmentItems[(string)$item->nav_item_id] = (float)$item->quantity_shipped;
            }

            $isPartialShipment = false;
            $shipmentItems = [];
            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyToShip() && !$item->getIsVirtual()
                    && isset($navShipmentItems[$item->getSku()]) && $navShipmentItems[$item->getSku()] > 0
                ) {
                    $shipmentItems[$item->getId()] = $navShipmentItems[$item->getSku()];
                    if ($item->getQtyToShip() > $navShipmentItems[$item->getSku()]) {
                        $isPartialShipment = true;
                    }
                }
            }

            $tracks = array();
            if (isset($entity->package_tracking)) {
                $systemCarriers = $this->getCarriers();

                foreach ($entity->package_tracking as $tracking) {
                    $carrier = strtolower((string)$tracking->shipping_carrier);
                    if (array_key_exists($carrier, $systemCarriers)) {
                        $tracks[] = [
                            'number'       => (string)$tracking->tracking_number,
                            'carrier_code' => $carrier,
                            'title'        => $systemCarriers[$carrier],
                        ];
                    } else {
                        $title = (string)$tracking->shipping_carrier . ' ' . (string)$tracking->shipping_method;
                        $title = ucwords($title);
                        $tracks[] = [
                            'number'       => (string)$tracking->tracking_number,
                            'carrier_code' => 'custom',
                            'title'        => $title,
                        ];
                    }
                }
            }

            /** @var \Magento\Sales\Model\Order\Shipment $shipment */
            $shipment = $this->shipmentFactory->create($order, $shipmentItems, $tracks);
            if (!$shipment) {
                throw new LocalizedException(__('Can\'t save the shipment for order #%1 right now.', $incrementId));
            }

            $validationResult = $this->shipmentValidator->validate($shipment, [QuantityValidator::class]);
            if ($validationResult->hasMessages()) {
                throw new LocalizedException(
                    __('Order #%1 - Shipment Document Validation Error(s):' . "\n" . implode("\n", $validationResult->getMessages()), $incrementId)
                );

            }

            $shipment->register();

            if ($this->config->get('shipment/create_invoice_with_shipment')) {
                $invoice = $this->malibuInvoice->createInvoice($order, $shipmentItems);
            }

            $shipment->getOrder()->setIsInProcess(true);
            $shipment->getOrder()->setSkipMconnect(true);

            $saveTransaction = $this->transactionFactory->create();

            if (isset($invoice)) {
                $saveTransaction->addObject($invoice);
            }
            $saveTransaction->addObject($shipment);
            $saveTransaction->addObject($shipment->getOrder());
            $saveTransaction->save();

            if (isset($invoice)) {
                $this->messages .= 'Order #' . $incrementId . ' invoiced, invoice #' . $invoice->getIncrementId() . "\n";
            }
            $this->messages .= 'Order #' . $incrementId . ' shipped, shipment #' . $shipment->getIncrementId() . "\n";

            // send invoice email
            try {
                if (isset($invoice) && $this->config->get('invoice/send_email_enabled')) {
                    $this->invoiceSender->send($invoice);
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage() . "\n";
            }

            // send shipment email
            try {
                if ($this->config->get('shipment/send_email_enabled')) {
                    $this->shipmentSender->send($shipment);
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage() . "\n";
            }

            // cancel remaining order items and complete the order
            try {
                if ($shipment && $isPartialShipment
                    && $this->config->get('shipment/cancel_remaining_not_shipped_items')
                ) {
                    if ($order->canCancel()) {
                        $order->cancel();
                        $order->save($order);
                        $this->messages .= 'Order #' . $incrementId . ' was completed, remaining not shipped items were canceled' . "\n";
                    }
                }
            } catch (\Exception $e) {
                $this->messages .= $e->getMessage() . "\n";
            }
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
            $this->messages .= 'Error while loading Order #' . $incrementId . ': ' . $e->getMessage();
        }

        return false;
    }

    protected function getCarriers()
    {
        $carriers = [];
        // @todo add store based retrieval $this->shippingConfig->getAllCarriers($this->getShipment()->getStoreId())
        $carrierInstances = $this->shippingConfig->getAllCarriers();
        $carriers['custom'] = __('Custom Value');
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[$code] = $carrier->getConfigData('title');
            }
        }
        return $carriers;
    }
}