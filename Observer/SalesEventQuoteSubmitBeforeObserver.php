<?php
namespace MalibuCommerce\MConnect\Observer;

class SalesEventQuoteSubmitBeforeObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var  \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if ($quote && $quote->getBillingAddress()) {
            $navId = $quote->getBillingAddress()->getNavId();
            if (!$navId && $quote->getShippingAddress()) {
                $navId = $quote->getShippingAddress()->getNavId();
            }
        }

        if (!empty($navId)) {
            foreach ($order->getAddresses() as $address) {
                $address->setNavId($navId);
            }
        }

        return $this;
    }
}
