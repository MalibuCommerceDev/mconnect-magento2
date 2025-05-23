<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\Data\OrderInterface;
use MalibuCommerce\MConnect\Model\SimpleXMLExtended;

class Order extends AbstractModel
{
    const PAYPAL_PAYMENT_METHODS = [
        'paypal_express',
        'paypal_express_bml',
        'braintree_paypal',
        'braintree_paypal_vault',
        'payment_services_paypal_smart_buttons',
        'payment_services_paypal_hosted_fields',
        'payment_services_paypal_google_pay',
        'payment_services_paypal_apple_pay',
        'payment_services_paypal_vault',
        'payflow_advanced',
        'payflow_link',
        'paypal_billing_agreement',
        'payflow_express',
        'hosted_pro',
        'payflowpro_cc_vault'
    ];

    /**
     * @var \Magento\Directory\Model\Region
     */
    protected $directoryRegion;

    /**
     * @var \Magento\Customer\Model\Address
     */
    protected $customerAddress;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\GiftMessage\Model\Message
     */
    protected $giftMessage;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * Order constructor.
     *
     * @param \Magento\Directory\Model\Region                 $directoryRegion
     * @param \Magento\Customer\Model\Address                 $customerAddress
     * @param \Magento\Customer\Model\CustomerFactory         $customerFactory
     * @param \Magento\GiftMessage\Model\Message              $giftMessage
     * @param \MalibuCommerce\MConnect\Model\Config           $config
     * @param Connection                                      $mConnectNavisionConnection
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\Serialize\Serializer\Json    $serializer
     * @param Manager                                         $moduleManager
     * @param \Psr\Log\LoggerInterface                        $logger
     * @param array                                           $data
     */
    public function __construct(
        \Magento\Directory\Model\Region $directoryRegion,
        \Magento\Customer\Model\Address $customerAddress,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\GiftMessage\Model\Message $giftMessage,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\Framework\Module\Manager $moduleManager,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->directoryRegion = $directoryRegion;
        $this->customerAddress = $customerAddress;
        $this->customerFactory = $customerFactory;
        $this->productMetadata = $productMetadata;
        $this->giftMessage = $giftMessage;
        $this->serializer = $serializer;
        $this->moduleManager = $moduleManager;

        parent::__construct($config, $mConnectNavisionConnection, $logger, $data);
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        return false;
    }

    /**
     * Export order to NAV (or if from NAV side - this is actually an order import from Magento)
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param int                                    $websiteId
     *
     * @return \SimpleXMLElement
     */
    public function import(\Magento\Sales\Api\Data\OrderInterface $orderEntity, $websiteId = 0)
    {
        $root = new SimpleXMLExtended('<sales_order_import />');

        $orderObject = $root->addChild('Order');

        $defaultNavId = $this->config->getWebsiteData('customer/default_nav_id_magento_guest', $websiteId);
        $customerDataModel = $this->customerFactory->create()->load($orderEntity->getCustomerId());
        if ($customerDataModel && $customerDataModel->getId()) {
            $defaultNavId = $this->config->getWebsiteData('customer/default_nav_id_magento_registered', $websiteId);
        }

        $orderObject->nav_customer_id = $customerDataModel && !empty($customerDataModel->getNavId())
            ? $customerDataModel->getNavId()
            : $defaultNavId;
        $orderObject->mag_order_id = $orderEntity->getIncrementId();
        $orderObject->mag_customer_id = $orderEntity->getCustomerId();
        $orderObject->email_address = $orderEntity->getCustomerEmail();
        $orderObject->store_id = $orderEntity->getStoreId();
        $tracksCollection = $orderEntity->getTracksCollection()->getFirstItem();
        if ($tracksCollection->getTrackNumber()) {
            $orderObject->tracking_no = $tracksCollection->getTrackNumber();
        }
        if (!empty($orderEntity->getCouponCode())) {
            $orderObject->promo_code = $orderEntity->getCouponCode();
        }

        $adminName = '';
        $isCDATAEnabled = $this->config->isCdataEnabledInExportXML($websiteId);
        foreach ($orderEntity->getStatusHistories() as $statusHistory) {
            $comment = (string)$statusHistory->getComment();
            // @todo better detect admin user like in core FrontAddCommentOnOrderPlacementPlugin plugin and store it in sales_order DB table to later utilize it here
            // Detect if order was placed by Admin user logged in as customer
            if (preg_match('~Order Placed by ([^\s]+).+ using Login as Customer~is', $comment, $matches)) {
                $adminName = $matches[1];
                continue;
            }
            if (!$statusHistory->getIsVisibleOnFront()) {
                continue;
            }

            if ($isCDATAEnabled) {
                $orderObject->addChild('sales_order_comment')->addCData($comment);
            } else {
                $orderObject->addChild('sales_order_comment', $comment);
            }
        }
        if ($isCDATAEnabled) {
            $orderObject->addChild('admin_name')->addCData($adminName);
        } else {
            $orderObject->admin_name = $adminName;
        }

        $orderObject->order_discount_amount = number_format((float)$orderEntity->getBaseDiscountAmount(), 2, '.', '');
        $orderObject->order_tax = number_format((float)$orderEntity->getBaseTaxAmount(), 2, '.', '');

        $this->addGiftOptions($orderEntity, $orderObject, $websiteId);
        $this->addRewardPoints($orderEntity, $orderObject, $websiteId);
        $this->addShipping($orderEntity, $orderObject);
        $this->addPayment($orderEntity, $orderObject);
        $this->addAddresses($orderEntity, $orderObject, $websiteId);
        $this->addItems($orderEntity, $orderObject);

        $this->addCustomAttributes($orderEntity, $orderObject);

        return $this->_import('order_import', $root, $websiteId);
    }

    /**
     * Add order custom attributes to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     *
     * @return $this
     */
    public function addCustomAttributes(\Magento\Sales\Api\Data\OrderInterface $orderEntity, \SimpleXMLElement &$root)
    {
        return $this;
    }

    /**
     * Add order payment data to NAV payload XML
     *
     * @param OrderInterface $orderEntity
     * @param \SimpleXMLElement $root
     *
     * @return Order
     */
    public function addPayment(OrderInterface $orderEntity, \SimpleXMLElement &$root)
    {
        $root->web_order_amt = $orderEntity->getOrderCurrencyCode() == $orderEntity->getStoreCurrencyCode()
            ? ($orderEntity->getBaseGrandTotal() ?: 0)
            : ($orderEntity->getGrandTotal() ?: 0);
        $payment = $orderEntity->getPayment();
        $root->payment_method = $payment !== false ? $payment->getMethod() : '';
        $root->po_number = $payment !== false ? $payment->getPoNumber() : '';
        if (!empty($payment) && in_array($payment->getMethod(), self::PAYPAL_PAYMENT_METHODS)) {
            $payPalTxnId = $payment->getAdditionalInformation('paypal_txn_id');
            $payPalTxnId = $payPalTxnId ?: $payment->getAdditionalInformation('paypal_order_id');
            $root->paypal_transaction_id = $payPalTxnId ?: ($payment->getLastTransId() ?: $payment->getCcTransId());
        }

        return $this;
    }

    /**
     * Add rewards options to NAV export XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     * @param int                                    $websiteId
     *
     * @return $this
     */
    public function addRewardPoints(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root, $websiteId = 0)
    {
        try {
            /**
             * Reward Points
             */
            if ($this->moduleManager->isEnabled('Magento_Reward')) {

                if ($orderEntity->getExtensionAttributes() && $orderEntity->getExtensionAttributes()->getRewardCurrencyAmount()) {
                    $root->rewards_amount = $orderEntity->getExtensionAttributes()->getRewardCurrencyAmount();
                }

                if ($orderEntity->getExtensionAttributes() && $orderEntity->getExtensionAttributes()->getRewardPointsBalance()) {
                    $root->rewards_points = $orderEntity->getExtensionAttributes()->getRewardPointsBalance();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning($e);
        }

        return $this;
    }

    /**
     * Add gift options to NAV export XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     * @param int                                    $websiteId
     *
     * @return $this
     */
    public function addGiftOptions(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root, $websiteId = 0)
    {
        try {
            $root->gift_wrap_message_to = '';
            $root->gift_wrap_message_from = '';
            $root->gift_wrap_message = '';
            $root->gift_wrap_charge = '';
            $root->gift_card_amt_used = '';
            $root->gift_card_number = '';

            /**
             * Gift Wrapping
             */
            if ($this->moduleManager->isEnabled('Magento_GiftWrapping')
                || $this->config->getWebsiteData('order/gift_wrapping_force_enabled', $websiteId)
            ) {
                $giftMessageId = $orderEntity->getGiftMessageId();
                $giftWrappingId = $orderEntity->getGwId();
                $giftWrappingPrintedCard = $orderEntity->getGwAddCard();
                $isGiftWrappingAmountSet = false;
                $giftWrappingAmount = 0.00;

                if ($giftMessageId) {
                    $this->giftMessage->load($giftMessageId);
                    if ($this->giftMessage->getId()) {
                        $root->gift_wrap_message_to = $this->giftMessage->getRecipient();
                        $root->gift_wrap_message_from = $this->giftMessage->getSender();
                        $root->gift_wrap_message = $this->giftMessage->getMessage();
                    }
                }

                if ($giftWrappingId) {
                    $giftWrappingAmount += $orderEntity->getGwBasePrice();
                    $isGiftWrappingAmountSet = true;
                }
                if ($giftWrappingPrintedCard) {
                    $isGiftWrappingAmountSet = true;
                    $giftWrappingAmount += $orderEntity->getGwCardBasePrice();
                }
                if ($isGiftWrappingAmountSet) {
                    $root->gift_wrap_charge = number_format((float)$giftWrappingAmount, 4, '.', '');
                }
            }

            /**
             * Gift Cards
             */
            if ($this->moduleManager->isEnabled('Magento_GiftCard')) {
                $giftCards = $orderEntity->getGiftCards();
                $giftCards = $giftCards ? $this->serializer->unserialize($giftCards) : [];
                if (!empty($giftCards)) {
                    $baseAmount = 0.00;
                    $codes = [];
                    foreach ($giftCards as $card) {
                        $codes[] = $card[\Magento\GiftCardAccount\Model\Giftcardaccount::CODE];
                        $baseAmount += $card[\Magento\GiftCardAccount\Model\Giftcardaccount::BASE_AMOUNT];
                    }

                    $root->gift_card_number = implode(', ', $codes);
                    $root->gift_card_amt_used = number_format((float)$baseAmount, 4, '.', '');
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning($e);
        }

        return $this;
    }

    /**
     * Add order addresses to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     * @param int                                    $websiteId
     */
    protected function addAddresses(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root, $websiteId = 0)
    {
        foreach ($orderEntity->getAddresses() as $address) {
            $this->addAddress($address, $orderEntity, $root, $websiteId);
        }
    }

    /**
     * Construct NAV address XML and set address data
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param \Magento\Sales\Api\Data\OrderInterface        $orderEntity
     * @param \SimpleXMLElement                             $root
     * @param int                                           $websiteId
     */
    protected function addAddress(
        \Magento\Sales\Api\Data\OrderAddressInterface $address,
        $orderEntity,
        &$root,
        $websiteId = 0
    ) {
        $child = $root->addChild('order_address');
        $specialNavId = explode('|', (string)$address->getNavId());
        $customerAddressId = $address->getCustomerAddressId();
        $child->mag_address_id = !empty($customerAddressId) ? $customerAddressId : $address->getEntityId();
        $child->nav_address_id = $specialNavId[2] ?? 'DEFAULT';
        $child->address_type = $address->getAddressType();
        if ($this->config->isCdataEnabledInExportXML($websiteId)) {
            $child->addChild('first_name')->addCData($address->getFirstname());
            $child->addChild('last_name')->addCData($address->getLastname());
            $child->addChild('company_name')->addCData($address->getCompany());
        } else {
            $child->first_name = $address->getFirstname();
            $child->last_name = $address->getLastname();
            $child->company_name = $address->getCompany();
        }

        $child->city = $address->getCity();
        $child->state = $this->directoryRegion->load($address->getRegionId())->getCode();
        $child->post_code = $address->getPostcode();
        $child->country = $address->getCountryId();
        $child->telephone = $address->getTelephone();
        $child->fax = $address->getFax();
        $this->setAdditionalAddressData($address, $orderEntity, $child);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param \Magento\Sales\Api\Data\OrderInterface        $orderEntity
     * @param \SimpleXMLElement                             $childElement
     *
     * @return \SimpleXMLElement
     */
    public function setAdditionalAddressData(
        \Magento\Sales\Api\Data\OrderAddressInterface $address,
        $orderEntity,
        $childElement
    ) {
        $street = $address->getStreet();
        $street1 = $street2 = '';

        if (!empty($street) && is_array($street)) {
            $street1 = array_shift($street);
        }

        if (count($street) > 0) {
            $street2 = implode(' ', $street);
        }

        $childElement->street = $street1;
        $childElement->street2 = $street2;

        return $childElement;
    }

    /**
     * Add order items to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     */
    protected function addItems(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        foreach ($orderEntity->getItems() as $item) {
            $this->addItem($orderEntity, $item, $root);
        }
    }

    /**
     * Construct NAV item XML and set item data
     *
     * @param \Magento\Sales\Api\Data\OrderInterface     $orderEntity
     * @param \Magento\Sales\Api\Data\OrderItemInterface $item
     * @param \SimpleXMLElement                          $root
     *
     * @return $this
     * @todo currently only simple, virtual and giftcard products are supported
     *
     */
    protected function addItem(
        \Magento\Sales\Api\Data\OrderInterface $orderEntity,
        \Magento\Sales\Api\Data\OrderItemInterface $item,
        &$root
    ) {
        if ($item->getProductType() == ProductType::TYPE_SIMPLE
            || ($this->moduleManager->isEnabled('Magento_GiftCard')
                && $item->getProductType() == \Magento\GiftCard\Model\Catalog\Product\Type\Giftcard::TYPE_GIFTCARD
            )
            || $item->getProductType() == ProductType::TYPE_VIRTUAL
        ) {
            $child = $root->addChild('order_item');

            $child->mag_item_id = $item->getSku();
            $child->name = $item->getName();
            $child->quantity = $item->getQtyOrdered();
            $currencyIsEqual = $orderEntity->getOrderCurrencyCode() == $orderEntity->getStoreCurrencyCode();
            if ($item->getParentItem() && ($item->getParentItem()->getProductType() != ProductType::TYPE_BUNDLE)) {
                $child->unit_price = $currencyIsEqual
                    ? $item->getParentItem()->getBasePrice()
                    : $item->getParentItem()->getPrice();
            } else {
                $child->unit_price = $currencyIsEqual
                    ? $item->getBasePrice()
                    : $item->getPrice();
            }
            if (!empty($item->getParentItem())) {
                $child->line_discount_amount = $currencyIsEqual
                    ? $item->getParentItem()->getBaseDiscountAmount()
                    : $item->getParentItem()->getDiscountAmount();
            } else {
                $child->line_discount_amount = $currencyIsEqual
                    ? $item->getBaseDiscountAmount()
                    : $item->getDiscountAmount();
            }
        }

        return $this;
    }

    /**
     * Construct NAV shipping information XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \SimpleXMLElement                      $root
     *
     * @return $this
     */
    protected function addShipping(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        $shippingAssignments = $orderEntity->getExtensionAttributes()->getShippingAssignments();
        /** @var \Magento\Sales\Api\Data\ShippingAssignmentInterface $shippingAssignment */
        if (isset($shippingAssignments)) {
            foreach ($shippingAssignments as $shippingAssignment) {
                $method = $shippingAssignment->getShipping()->getMethod();
            }
        }
        if (!isset($method)) {
            $root->shipping_carrier = 'none';
            $root->shipping_method = 'none';
            $root->shipping_amount = '0.0000';

            return $this;
        }

        $bits = explode('_', (string)$method);
        $root->shipping_carrier = $bits[0];
        $root->shipping_method = implode('_', array_slice($bits, 1));
        $root->shipping_amount = $orderEntity->getBaseShippingAmount();

        return $this;
    }
}
