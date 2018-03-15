<?php

namespace MalibuCommerce\MConnect\Model\Navision;

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
     * Order constructor.
     *
     * @param \Magento\Directory\Model\Region         $directoryRegion
     * @param \Magento\Customer\Model\Address         $customerAddress
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     * @param \Magento\GiftMessage\Model\Message      $giftMessage
     * @param \MalibuCommerce\MConnect\Model\Config   $config
     * @param Connection                              $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                $logger
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Directory\Model\Region $directoryRegion,
        \Magento\Customer\Model\Address $customerAddress,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\GiftMessage\Model\Message $giftMessage,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->directoryRegion = $directoryRegion;
        $this->customerAddress = $customerAddress;
        $this->customerFactory = $customerFactory;
        $this->giftMessage = $giftMessage;

        parent::__construct($config, $mConnectNavisionConnection, $logger);
    }

    public function import(\Magento\Sales\Api\Data\OrderInterface $orderEntity)
    {
        $root = new \simpleXMLElement('<sales_order_import />');

        $orderObject = $root->addChild('Order');

        $customerDataModel = $this->customerFactory->create()->load($orderEntity->getCustomerId());
        $orderObject->nav_customer_id = $customerDataModel && $customerDataModel->getId() ? $customerDataModel->getNavId() : '';
        $orderObject->mag_order_id = $orderEntity->getIncrementId();
        $orderObject->mag_customer_id = $orderEntity->getCustomerId();
        $orderObject->email_address = $orderEntity->getCustomerEmail();
        $orderObject->store_id = $orderEntity->getStoreId();

        $orderObject->gift_wrap_message_to = '';
        $orderObject->gift_wrap_message_from = '';
        $orderObject->gift_wrap_message = '';
        $orderObject->gift_wrap_charge = '';

        if ($giftMessageId = $orderEntity->getGiftMessageId()) {
            $this->giftMessage->load($giftMessageId);
            if ($this->giftMessage->getId()) {
                $orderObject->gift_wrap_message_to = $this->giftMessage->getRecipient();
                $orderObject->gift_wrap_message_from = $this->giftMessage->getSender();
                $orderObject->gift_wrap_message = $this->giftMessage->getMessage();
                $orderObject->gift_wrap_charge = $orderEntity->getGwBasePrice();
            }
        }

        $this->addShipping($orderEntity, $orderObject);

        $payment = $orderEntity->getPayment();
        $orderObject->payment_method = $payment !== false ? $payment->getMethod() : '';

        $orderObject->order_discount_amount = $orderEntity->getBaseDiscountAmount();

        $this->addAddresses($orderEntity, $orderObject);
        $this->addItems($orderEntity, $orderObject);

        return $this->_import('order_import', $root);
    }

    protected function addAddresses(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        foreach ($orderEntity->getAddresses() as $address) {
            $this->addAddress($address, $root);
        }
    }

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

    protected function addItems(\Magento\Sales\Api\Data\OrderInterface $orderEntity, &$root)
    {
        foreach ($orderEntity->getItems() as $item) {
            $this->addItem($item, $root);
        }
    }

    protected function addItem(\Magento\Sales\Api\Data\OrderItemInterface $item, &$root)
    {
        $child = $root->addChild('order_item');

        $child->mag_item_id = $item->getSku();
        $child->name = $item->getName();
        $child->quantity = $item->getQtyOrdered();
        $child->unit_price = $item->getBasePrice();
        $child->line_discount_amount = $item->getBaseDiscountAmount();
    }

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