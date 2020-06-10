<?php
namespace MalibuCommerce\MConnect\Observer;

class SalesEventQuoteSubmitBeforeObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config
    ) {
        $this->config = $config;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isModuleEnabled()) {

            return $this;
        }

        /** @var  \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var  \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        $navId = null;
        if ($quote && $quote->getBillingAddress()) {
            $navId = $quote->getBillingAddress()->getNavId();
        }
        if (empty($navId) && $quote && $quote->getShippingAddress()) {
            $navId = $quote->getShippingAddress()->getNavId();
        }

        if (!empty($navId)) {
            foreach ($order->getAddresses() as $address) {
                $address->setNavId($navId);
            }
        }

        return $this;
    }
}
