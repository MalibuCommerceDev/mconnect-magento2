<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment\Validation\QuantityValidator;

class Shipment extends \MalibuCommerce\MConnect\Model\Queue
{
    const SHIPPING_TITLE_DEFAULT = 'M-Connect';

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

    /** @var \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface  */
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
     * @var \MalibuCommerce\MConnect\Model\Config|Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Shipment $navShipment,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Sales\Model\Order\Shipment\ShipmentValidatorInterface $shipmentValidator,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $shipmentSender,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory
    ) {
        $this->navShipment = $navShipment;
        $this->orderFactory = $orderFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentValidator = $shipmentValidator;
        $this->transactionFactory = $transactionFactory;
        $this->shipmentSender = $shipmentSender;
        $this->config = $config;
        $this->queueFlagFactory = $queueFlagFactory;
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
                $count++;
                try {
                    $import = $this->importSipment($data);
                } catch (\Exception $e) {
                    $this->messages .= $e->getMessage();
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));
        $this->setLastSyncTime(Flag::FLAG_CODE_LAST_SHIPMENT_SYNC_TIME, $lastSync);
        $this->messages .= PHP_EOL . 'Processed ' . $count . ' shipments(s).';
    }

    protected function importSipment(\SimpleXMLElement $entity)
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

            $navShipmnetItems = array();
            foreach ($entity->shipment_item as $item) {
                $navShipmnetItems[(string)$item->nav_item_id] = (float)$item->quantity_shipped;
            }

            $shipmentItems = [];
            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyToShip() && !$item->getIsVirtual()
                    && isset($navShipmnetItems[$item->getSku()]) && $navShipmnetItems[$item->getSku()] > 0
                ) {
                    $shipmentItems[$item->getId()] = $navShipmnetItems[$item->getSku()];
                }
            }


            $tracks = array();
            if (isset($entity->package_tracking)) {
                foreach ($entity->package_tracking as $tracking) {
                    $tracks[] = [
                        'number'       => (string)$tracking->tracking_number,
                        'carrier_code' => 'custom',
                        'title'        => self::SHIPPING_TITLE_DEFAULT,
                    ];
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
            $shipment->getOrder()->setIsInProcess(true);
            $shipment->getOrder()->setSkipMconnect(true);

            $saveTransaction = $this->transactionFactory->create();
            $saveTransaction->addObject(
                $shipment
            )->addObject(
                $shipment->getOrder()
            );
            $saveTransaction->save();

            $this->messages .= 'Order #' . $incrementId . ' shipped, shipment #' . $shipment->getIncrementId();

            // send shipment email
            try {
                if ($this->config->get('shipment/send_email_enabled')) {
                    $this->shipmentSender->send($shipment);
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