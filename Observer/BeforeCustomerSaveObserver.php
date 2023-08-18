<?php

namespace MalibuCommerce\MConnect\Observer;

class BeforeCustomerSaveObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Set default NAV ID for new customers if needed
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return \MalibuCommerce\MConnect\Observer\BeforeCustomerSaveObserver
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if (!$this->config->isModuleEnabled()) {

                return $this;
            }

            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getCustomer();
            if (!$customer || !$customer->getId() || $customer->getSkipMconnect()) {

                return $this;
            }
            $websiteId = $customer->getWebsiteId();
            $navId = $this->config->getWebsiteData('customer/default_nav_id_magento_registered', $websiteId);
            if (!$navId) {

                return $this;
            }
            $customer->setNavId($navId);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        return $this;
    }
}
