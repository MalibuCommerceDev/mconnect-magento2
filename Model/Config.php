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

    public function getFlag($data, $store = null)
    {
        return boolval($this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $data,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        ));
    }

    public function get($data, $store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $data,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function isModuleEnabled()
    {
        return $this->getFlag('general/enabled');
    }

    public function getErrorRecipients()
    {
        return array_map('trim', explode(',', $this->get('nav_connection/error_recipient')));
    }

    public function getNavConnectionId($store = null)
    {
        return 1;
    }

    public function getNavConnectionUrl($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    public function getNavConnectionUsername($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/username',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    public function getNavConnectionPassword($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    public function getUseNtlmAuthentication($store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/ntlm',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Get decrypted trigger password
     *
     * @param null $store
     *
     * @return string
     */
    public function getTriggerPassword($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/trigger_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getIsInsecureConnectionAllowed($store = null)
    {
        return $this->get('nav_connection/allow_insecure_connection', $store);
    }

    public function getConnectionTimeout($store = null)
    {
        $timeout = (int)$this->get('nav_connection/connection_timeout', $store);
        if ($timeout <= 0) {
            return self::DEFAULT_NAV_CONNECTION_TIMEOUT;
        }

        return $timeout;
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

    public function getIsHoldNewOrdersExport($store = null)
    {
        return $this->get('order/hold_new_orders_export', $store);
    }

    public function getHoldNewOrdersDelay($store = null)
    {
        $delay = (int)$this->get('order/hold_new_orders_delay', $store);

        if (!$this->getIsHoldNewOrdersExport() && $this->shouldNewOrdersBeForcefullyHolden()) {
            $delay = self::DEFAULT_NEW_ORDERS_DELAY;
        }

        return $delay;
    }

    public function getOrderStatusWhenSyncedToNav($store = null)
    {
        return $this->get('order/order_status_when_synced_to_nav', $store);
    }

    public function getOrderStatuesAllowedForExportToNav($store = null)
    {
        return explode(',', $this->get('order/allowed_order_statuses_to_export', $store));
    }

    public function getNAVReportsCustomerGroups($store = null)
    {
        return explode(',', $this->get('customer/nav_reports_allowed_customer_groups', $store));
    }
}
