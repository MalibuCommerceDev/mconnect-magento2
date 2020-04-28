<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;

class Shipment extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'shipment';
    const NAV_XML_NODE_ITEM_NAME = 'shipment';

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

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navShipment, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     */
    public function importShipment($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $incrementId = (string)$data->mag_order_id;
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder($incrementId);

        try {
            if (!$order || !$order->getId()) {
                throw new LocalizedException(__('The order #%1 no longer exists.', $incrementId));
            }
            $saveTransaction = $this->transactionFactory->create();

            $isShipmentPartial = $this->isShipmentPartial($order, $data);
            $shipment = $this->initShipment($order, $data);

            if ($this->config->get($this->getQueueCode() . '/create_invoice_with_shipment')) {
                try {
                    $invoice = $this->initInvoice($order, $data);
                } catch (\LogicException $e) {
                    // Ignore logical exceptions when attempting to create an invoice.
                    // This is needed when for ex. an invoice already exists and "Create Invoice With Shipment" is ON.
                } catch (\Throwable $e) {
                    throw $e;
                }
            }

            if ($shipment) {
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->getOrder()->setSkipMconnect(true);
            } elseif (isset($invoice)) {
                $invoice->getOrder()->setIsInProcess(true);
                $invoice->getOrder()->setSkipMconnect(true);
            }

            if (isset($invoice)) {
                $saveTransaction->addObject($invoice);
            }
            if ($shipment) {
                $saveTransaction->addObject($shipment);
            }
            if ($shipment) {
                $saveTransaction->addObject($shipment->getOrder());
            } elseif (isset($invoice)) {
                $saveTransaction->addObject($invoice->getOrder());
            }
            if ($shipment || isset($invoice)) {
                $saveTransaction->save();
            }

            if (isset($invoice)) {
                $this->messages .= 'Order #' . $incrementId . ' invoiced, invoice #' . $invoice->getIncrementId() . "\n";
            }
            if ($shipment) {
                $this->messages .= 'Order #' . $incrementId . ' shipped, shipment #' . $shipment->getIncrementId() . "\n";
            }

            // send invoice email
            try {
                if (isset($invoice) && $this->config->get('invoice/send_email_enabled')) {
                    $this->invoiceSender->send($invoice);
                }
            } catch (\Throwable $e) {
                $this->messages .= $e->getMessage() . "\n";
            }

            // send shipment email
            try {
                if ($shipment && $this->config->get($this->getQueueCode() . '/send_email_enabled')) {
                    $this->shipmentSender->send($shipment);
                }
            } catch (\Throwable $e) {
                $this->messages .= $e->getMessage() . "\n";
            }

            // cancel remaining order items and complete the order
            try {
                if ($shipment && $isShipmentPartial
                    && $this->config->get($this->getQueueCode() . '/cancel_remaining_not_shipped_items')
                ) {
                    if ($order->canCancel()) {
                        $order->cancel();
                        $order->save($order);
                        $this->messages .= 'Order #' . $incrementId . ' was completed, remaining not shipped items were canceled' . "\n";
                    }
                }
            } catch (\Throwable $e) {
                $this->messages .= $e->getMessage() . "\n";
            }
        } catch (\Throwable $e) {
            throw $e;
        }

        return true;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \SimpleXMLElement $navEntity
     *
     * @return \Magento\Sales\Model\Order\Shipment|null
     * @throws LocalizedException.
     */
    protected function initShipment($order, $navEntity)
    {
        if ($order->getIsVirtual() && $this->config->get($this->getQueueCode() . '/create_invoice_with_shipment')
            && $this->config->get($this->getQueueCode() . '/skip_shipment_but_invoice_for_virtual_orders')
        ) {
            return null;
        }

        if (!$order->canShip()) {
            throw new LocalizedException(
                __('The order #%1 does not allow a shipment to be created.', $order->getIncrementId())
            );
        }

        $shipmentItems = $this->getShippingItems($order, $navEntity);

        $tracks = [];
        if (isset($navEntity->package_tracking)) {
            $systemCarriers = $this->getCarriers();

            foreach ($navEntity->package_tracking as $tracking) {
                $carrier = strtolower((string)$tracking->shipping_carrier);
                $trackingNumber = (string)$tracking->tracking_number;
                if (!$trackingNumber && $this->config->isEnableCreateShipmentWithoutTrackingNumber()) {
                    $trackingNumber = '-';
                }
                if (array_key_exists($carrier, $systemCarriers)) {
                    $tracks[] = [
                        'number'       => $trackingNumber,
                        'carrier_code' => $carrier,
                        'title'        => $systemCarriers[$carrier],
                    ];
                } else {
                    $title = (string)$tracking->shipping_carrier . ' ' . (string)$tracking->shipping_method;
                    $title = ucwords($title);
                    $tracks[] = [
                        'number'       => $trackingNumber,
                        'carrier_code' => 'custom',
                        'title'        => $title,
                    ];
                }
            }
        }

        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $this->shipmentFactory->create($order, $shipmentItems, $tracks);
        if (!$shipment) {
            throw new LocalizedException(__('Can\'t save the shipment for order #%1 right now.', $order->getIncrementId()));
        }

        $validationResult = $this->shipmentValidator->validate($shipment, [QuantityValidator::class]);
        if ($validationResult->hasMessages()) {
            throw new LocalizedException(
                __('Order #%1 - Shipment Document Validation Error(s):' . "\n" . implode("\n", $validationResult->getMessages()), $order->getIncrementId())
            );

        }

        $shipment->register();

        return $shipment;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \SimpleXMLElement $navEntity
     *
     * @return array
     */
    protected function getShippingItems($order, $navEntity)
    {
        $navShipmentItems = [];
        foreach ($navEntity->shipment_item as $item) {
            $sku = (string)$item->nav_item_id;
            $sku = strtolower(trim($sku));
            $navShipmentItems[$sku] = (float)$item->quantity_shipped;
        }

        $shipmentItems = [];
        foreach ($order->getAllItems() as $item) {
            $sku = $item->getSku();
            $sku = strtolower(trim($sku));

            if ($item->getProductType() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE
                && $item->getChildrenItems() && count($item->getChildrenItems()) > 0
            ) {
                foreach ($item->getChildrenItems() as $childItem) {
                    $childrItemSku =  strtolower(trim($childItem->getSku()));
                    if (array_key_exists($childrItemSku, $navShipmentItems)) {
                        $shipmentItems[$item->getId()] = $navShipmentItems[$childrItemSku];
                        break;
                    }
                }
            } else {
                if ($item->getQtyToShip() && !$item->getIsVirtual()
                    && isset($navShipmentItems[$sku]) && $navShipmentItems[$sku] > 0
                ) {
                    $shipmentItems[$item->getId()] = $navShipmentItems[$sku];
                }
            }
        }

        return $shipmentItems;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \SimpleXMLElement $navEntity
     *
     * @return bool
     */
    protected function isShipmentPartial($order, $navEntity)
    {
        $navShipmentItems = [];
        foreach ($navEntity->shipment_item as $item) {
            $sku = (string)$item->nav_item_id;
            $sku = strtolower(trim($sku));
            $navShipmentItems[$sku] = (float)$item->quantity_shipped;
        }

        foreach ($order->getAllItems() as $item) {
            $sku = $item->getSku();
            $sku = strtolower(trim($sku));
            if (isset($navShipmentItems[$sku]) && !$item->getIsVirtual()
                && $item->getQtyToShip() > $navShipmentItems[$sku]
            ) {
                return true;
            }

            if (!isset($navShipmentItems[$sku]) && !$item->getIsVirtual()) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \SimpleXMLElement $navEntity
     *
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws LocalizedException
     */
    protected function initInvoice($order, $navEntity)
    {
        return $this->malibuInvoice->createInvoice($order, $this->getInvoiceItems($order, $navEntity));
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param \SimpleXMLElement $navEntity
     *
     * @return array
     */
    protected function getInvoiceItems($order, $navEntity)
    {
        $navInvoiceItems = [];
        foreach ($navEntity->shipment_item as $item) {
            $sku = (string)$item->nav_item_id;
            $sku = strtolower(trim($sku));
            $navInvoiceItems[$sku] = (float)$item->quantity_shipped;
        }

        $invoiceItems = [];
        foreach ($order->getAllItems() as $item) {
            $sku = $item->getSku();
            $sku = strtolower(trim($sku));
            if ($item->getQtyToInvoice() && isset($navInvoiceItems[$sku]) && $navInvoiceItems[$sku] > 0) {
                $invoiceItems[$item->getId()] = $navInvoiceItems[$sku];
            }
        }

        return $invoiceItems;
    }

    /**
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
