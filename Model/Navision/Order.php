<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use Magento\Catalog\Model\Product\Type as ProductType;

class Order extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
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
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->directoryRegion = $directoryRegion;
        $this->customerAddress = $customerAddress;
        $this->customerFactory = $customerFactory;
        $this->productMetadata = $productMetadata;
        $this->giftMessage = $giftMessage;
        $this->serializer = $serializer;

        parent::__construct($config, $mConnectNavisionConnection, $logger);
    }

    /**
     * Export order to NAV (or if from NAV side - this is actually an order import from Magento)
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     *
     * @return \simpleXMLElement
     */
    public function import(\Magento\Sales\Api\Data\OrderInterface $orderEntity)
    {
        $root = new \simpleXMLElement('<sales_order_import />');

        $orderObject = $root->addChild('Order');

        $customerDataModel = $this->customerFactory->create()->load($orderEntity->getCustomerId());

        $defaultNavId = $this->config->get('customer/default_nav_id', $orderEntity->getStoreId());
        $orderObject->nav_customer_id = $customerDataModel && $customerDataModel->getId()
            ? $customerDataModel->getNavId()
            : $defaultNavId;
        $orderObject->mag_order_id = $orderEntity->getIncrementId();
        $orderObject->mag_customer_id = $orderEntity->getCustomerId();
        $orderObject->email_address = $orderEntity->getCustomerEmail();
        $orderObject->store_id = $orderEntity->getStoreId();

        $this->addGiftOptions($orderEntity, $orderObject);
        $this->addShipping($orderEntity, $orderObject);

        $payment = $orderEntity->getPayment();
        $orderObject->payment_method = $payment !== false ? $payment->getMethod() : '';

        $orderObject->order_discount_amount = $orderEntity->getBaseDiscountAmount();

        $this->addAddresses($orderEntity, $orderObject);
        $this->addItems($orderEntity, $orderObject);

        return $this->_import('order_import', $root);
    }

    /**
     * Add gift options to NAV export XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \simpleXMLElement $root
     *
     * @return $this
     */
    public function addGiftOptions(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        try {
            $root->gift_wrap_message_to = '';
            $root->gift_wrap_message_from = '';
            $root->gift_wrap_message = '';
            $root->gift_wrap_charge = '';
            $root->gift_card_amt_used = '';
            $root->gift_card_number = '';

            if (!$this->isCommerceEdition()) {
                return $this;
            }

            /**
             * Gift Wrapping
             */
            $giftMessageId = $orderEntity->getGiftMessageId();
            $giftWrappingId = $orderEntity->getGwId();
            $giftWrappingPrintedCard = $orderEntity->getGwAddCard();
            $isGiftWrappingAmountSet = false;
            $giftWrappingAmount = 0.00;
            $giftCards = $orderEntity->getGiftCards();

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
                $root->gift_wrap_charge = number_format((float) $giftWrappingAmount, 4, '.', '');
            }

            /**
             * Gift Cards
             */
            $giftCards = $giftCards ? $this->serializer->unserialize($giftCards) : [];
            if (!empty($giftCards)) {
                $baseAmount = 0.00;
                $codes = [];
                foreach ($giftCards as $card) {
                    $codes[] = $card[\Magento\GiftCardAccount\Model\Giftcardaccount::CODE];
                    $baseAmount += $card[\Magento\GiftCardAccount\Model\Giftcardaccount::BASE_AMOUNT];
                }

                $root->gift_card_number = implode(', ', $codes);
                $root->gift_card_amt_used = number_format((float) $baseAmount, 4, '.', '');
            }
        } catch (\Exception $e) {
            // Ignore exceptions
        }

        return $this;
    }

    /**
     * Check if Commerce features available in current Magento installation
     *
     * @return bool
     */
    protected function isCommerceEdition()
    {
        return $this->productMetadata->getEdition() != 'Community';
    }

    /**
     * Add order addresses to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \simpleXMLElement $root
     */
    protected function addAddresses(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        foreach ($orderEntity->getAddresses() as $address) {
            $this->addAddress($address, $root);
        }
    }

    /**
     * Construct NAV address XML and set address data
     *
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $address
     * @param \simpleXMLElement $root
     */
    protected function addAddress(\Magento\Sales\Api\Data\OrderAddressInterface $address, &$root)
    {
        $child = $root->addChild('order_address');
        $navId = $address->getNavId();
        $child->mag_address_id = $address->getEntityId();
        $child->nav_address_id = empty($navId) ? 'default' : $navId;
        $child->address_type = $address->getAddressType();
        $child->first_name = $address->getFirstname();
        $child->last_name = $address->getLastname();
        $child->company_name = $address->getCompany();
        $child->street = implode(' ', $address->getStreet());
        $child->city = $address->getCity();
        $child->state = $this->directoryRegion->load($address->getRegionId())->getCode();
        $child->post_code = $address->getPostcode();
        $child->country = $address->getCountryId();
        $child->telephone = $address->getTelephone();
        $child->fax = $address->getFax();
    }

    /**
     * Add order items to NAV payload XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \simpleXMLElement $root
     */
    protected function addItems(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        foreach ($orderEntity->getItems() as $item) {
            $this->addItem($item, $root);
        }
    }

    /**
     * Construct NAV item XML and set item data
     *
     * @param \Magento\Sales\Api\Data\OrderItemInterface $item
     * @param \simpleXMLElement $root
     *
     * @return $this
     */
    protected function addItem(\Magento\Sales\Api\Data\OrderItemInterface $item, &$root)
    {
        /**
         * Add only simple products to NAV
         */
        if ($item->getProductType() != ProductType::TYPE_SIMPLE) {
            return $this;
        }

        $child = $root->addChild('order_item');

        $child->mag_item_id = $item->getSku();
        $child->name = $item->getName();
        $child->quantity = $item->getQtyOrdered();
        $child->unit_price = $item->getParentItem() ? $item->getParentItem()->getBasePrice() : $item->getBasePrice();
        $child->line_discount_amount = $item->getParentItem() ? $item->getParentItem()->getBaseDiscountAmount() : $item->getBaseDiscountAmount();

        return $this;
    }

    /**
     * Construct NAV shipping information XML
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $orderEntity
     * @param \simpleXMLElement $root
     *
     * @return $this
     */
    protected function addShipping(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        $shippingAssignments = $orderEntity->getExtensionAttributes()->getShippingAssignments();
        /** @var \Magento\Sales\Api\Data\ShippingAssignmentInterface $shippingAssignment */
        foreach ($shippingAssignments as $shippingAssignment) {
            $method = $shippingAssignment->getShipping()->getMethod();
        }

        if (!isset($method)) {
            $root->shipping_carrier = 'none';
            $root->shipping_method = 'none';
            $root->shipping_amount = '0.0000';

            return $this;
        }

        $bits = explode('_', $method);
        $root->shipping_carrier = $bits[0];
        $root->shipping_method = implode('_', array_slice($bits, 1));
        $root->shipping_amount = $orderEntity->getBaseShippingAmount();

        return $this;
    }
}