<?php
namespace MalibuCommerce\MConnect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use MalibuCommerce\MConnect\Model\Config;

class SalesEventQuoteSubmitBeforeObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * SalesEventQuoteSubmitBeforeObserver constructor.
     *
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isModuleEnabled()) {

            return $this;
        }

        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var  Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        $billingAddressNavId = $shippingAddressNavId = null;
        if ($quote && $quote->getBillingAddress()) {
            $billingAddressNavId = $quote->getBillingAddress()->getNavId();
        }
        if ($quote && $quote->getShippingAddress()) {
            $shippingAddressNavId = $quote->getShippingAddress()->getNavId();
        }

        foreach ($order->getAddresses() as $address) {
            if ($address->getAddressType() == Address::TYPE_BILLING && !empty($billingAddressNavId)) {
                $address->setNavId($billingAddressNavId);
            }

            if ($address->getAddressType() == Address::TYPE_SHIPPING && !empty($shippingAddressNavId)) {
                $address->setNavId($shippingAddressNavId);
            }
        }

        return $this;
    }
}
