<?php

namespace MalibuCommerce\MConnect\Helper;

class NavReports extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * NavReports constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     * @param \Magento\Customer\Model\Session       $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    public function areNavReportsEnabled($store = null)
    {
        $customerGroup = $this->customerSession->getCustomerGroupId();
        $websiteId = $this->customerSession->getCustomer()->getWebsiteId();
        return $this->mConnectConfig->isModuleEnabled()
               && (bool)$this->mConnectConfig->getWebsiteData('customer/show_reports', $websiteId)
               && in_array($customerGroup, $this->mConnectConfig->getNAVReportsCustomerGroups($websiteId));
    }
}
