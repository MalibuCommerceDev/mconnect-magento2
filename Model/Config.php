<?php

namespace MalibuCommerce\MConnect\Model;

class Config
{
    const XML_PATH_CONFIG_SECTION        = 'malibucommerce_mconnect';
    const DEFAULT_NAV_CONNECTION_TIMEOUT = 10; // in seconds
    const DEFAULT_NEW_ORDERS_DELAY       = 5; // in minutes

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Registry                        $registry
     * @param \Magento\Framework\Module\Manager                  $moduleManager
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return bool
     */
    public function isModuleEnabled()
    {
        return $this->getFlag('general/enabled');
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return int
     */
    public function getNavConnectionId($store = null)
    {
        return 1;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getNavConnectionUrl($store = null)
    {
        return $this->get('nav_connection/url', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getNavConnectionUsername($store = null)
    {
        return $this->get('nav_connection/username', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getNavConnectionPassword($store = null)
    {
        return $this->get('nav_connection/password', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return bool
     */
    public function getUseNtlmAuthentication($store = null)
    {
        return $this->getFlag('nav_connection/ntlm', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return bool
     */
    public function getIsInsecureConnectionAllowed($store = null)
    {
        return $this->getFlag('nav_connection/allow_insecure_connection', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return int
     */
    public function getConnectionTimeout($store = null)
    {
        $timeout = (int)$this->get('nav_connection/connection_timeout', $store);
        if ($timeout <= 0) {
            return self::DEFAULT_NAV_CONNECTION_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * Get decrypted trigger password
     *
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getTriggerPassword($store = null)
    {
        return $this->get('nav_connection/trigger_password', $store);
    }

    /**
     * When Signifyd Integration is enabled, it will put any fraudulent orders on hold
     * but that happens asynchronously to how order are being placed into the Mconnect queue for NAV export.
     *
     * Thus we need to delay orders export forcefully, so that until Magento_Signifyd puts new orders on hold
     * they are not being accidentally exported to NAV yet.
     *
     * When forced order export delay is activated but Mconnect "Hold New Orders Export" is not enabled,
     * there will be a corresponding message next to the config field "Hold New Orders Export" in Admin Panel.
     *
     * Note: older Magento versions are not shipped with Magento_Signifyd
     *
     * @return bool
     */
    public function shouldNewOrdersBeForcefullyHolden()
    {
        if ($this->moduleManager->isEnabled('Magento_Signifyd')) {
            $enabled = $this->scopeConfig->isSetFlag(
                'fraud_protection/signifyd/active',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            return $enabled;
        }

        return false;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return bool
     */
    public function getIsHoldNewOrdersExport($store = null)
    {
        return $this->getFlag('order/hold_new_orders_export', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return int
     */
    public function getHoldNewOrdersDelay($store = null)
    {
        $delay = (int)$this->get('order/hold_new_orders_delay', $store);

        if (!$this->getIsHoldNewOrdersExport() && $this->shouldNewOrdersBeForcefullyHolden()) {
            $delay = self::DEFAULT_NEW_ORDERS_DELAY;
        }

        return $delay;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getOrderStatusWhenSyncedToNav($store = null)
    {
        return $this->get('order/order_status_when_synced_to_nav', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getOrderStatuesAllowedForExportToNav($store = null)
    {
        return explode(',', $this->get('order/allowed_order_statuses_to_export', $store));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getNAVReportsCustomerGroups($store = null)
    {
        return explode(',', $this->get('customer/nav_reports_allowed_customer_groups', $store));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return bool
     */
    public function isErrorEmailingEnabled($store = null)
    {
        return $this->getFlag('nav_connection/send_error_emails', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getErrorEmailSender($store = null)
    {
        return $this->get('nav_connection/error_email_sender', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return array
     */
    public function getErrorRecipients($store = null)
    {
        return array_map('trim', explode(',', $this->get('nav_connection/error_email_recipient', $store)));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getErrorEmailTemplate($store = null)
    {
        return $this->get('nav_connection/error_email_template', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return bool
     */
    public function isNewCustomerPasswordResetEmailingEnabled($store = null)
    {
        return $this->getFlag('customer/send_new_customer_emails', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getNewCustomerPasswordResetEmailSender($store = null)
    {
        return $this->get('customer/new_customer_email_sender', $store);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Store $store
     *
     * @return string
     */
    public function getNewCustomerPasswordResetEmailTemplate($store = null)
    {
        return $this->get('customer/new_customer_email_template', $store);
    }

    public function getFlag($path, $store = null)
    {
        return boolval($this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        ));
    }

    public function get($path, $store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
