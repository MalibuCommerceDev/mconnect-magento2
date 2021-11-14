<?php

namespace MalibuCommerce\MConnect\Model;

use MalibuCommerce\MConnect\Model\Adminhtml\Config\Backend\Cron\SyncSchedule;
use MalibuCommerce\MConnect\Model\Queue\Customer as CustomerModel;
use MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;

class Config
{
    const XML_PATH_CONFIG_SECTION        = 'malibucommerce_mconnect';
    const DEFAULT_NAV_CONNECTION_TIMEOUT = 10; // in seconds
    const DEFAULT_NAV_REQUEST_TIMEOUT    = 10; // in seconds
    const DEFAULT_NEW_ORDERS_DELAY       = 5; // in minutes

    const AUTH_METHOD_NTLM   = 1;
    const AUTH_METHOD_DIGEST = 2;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /** @var \Magento\Framework\Registry */
    protected $registry;

    /** @var \Magento\Framework\Module\Manager */
    protected $moduleManager;

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
     * @return bool
     */
    public function getIsSoapDebugEnabled()
    {
        return $this->getFlag('nav_connection/soap_debug');
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getNavConnectionUrl($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/url', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getNavConnectionUsername($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/username', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getNavConnectionPassword($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/password', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool|int
     */
    public function getAuthenticationMethod($websiteId = null)
    {
        $value = $this->getWebsiteData('nav_connection/ntlm', $websiteId);

        switch ($value) {
            case self::AUTH_METHOD_NTLM:
                return CURLAUTH_NTLM;
            case self::AUTH_METHOD_DIGEST:
                return CURLAUTH_DIGEST;
            default:
                return false;
        }
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function getIsInsecureConnectionAllowed($websiteId = null)
    {
        return (bool)$this->getWebsiteData('nav_connection/allow_insecure_connection', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getConnectionTimeout($websiteId = null)
    {
        $timeout = (int)$this->getWebsiteData('nav_connection/connection_timeout', $websiteId);
        if ($timeout <= 0) {
            return self::DEFAULT_NAV_CONNECTION_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getRequestTimeout($websiteId = null)
    {
        $timeout = (int)$this->getWebsiteData('nav_connection/request_timeout', $websiteId);
        if ($timeout <= 0) {
            return self::DEFAULT_NAV_REQUEST_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * Get decrypted trigger password
     *
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getTriggerPassword($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/trigger_password', $websiteId);
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
    public function shouldNewOrdersBeForcefullyHeld()
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
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function getIsHoldNewOrdersExport($websiteId = null)
    {
        return (bool)$this->getWebsiteData('order/hold_new_orders_export', $websiteId)
               && !$this->isScheduledOrdersExportEnabled();
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getHoldNewOrdersDelay($websiteId = null)
    {
        $delay = (int)$this->getWebsiteData('order/hold_new_orders_delay', $websiteId);

        if (!$this->getIsHoldNewOrdersExport($websiteId) && $this->shouldNewOrdersBeForcefullyHeld()) {
            $delay = self::DEFAULT_NEW_ORDERS_DELAY;
        }

        return $delay;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getOrderStatusWhenSyncedToNav($websiteId = null)
    {
        return $this->getWebsiteData('order/order_status_when_synced_to_nav', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getOrderStatuesAllowedForExportToNav($websiteId = null)
    {
        return explode(',', $this->getWebsiteData('order/allowed_order_statuses_to_export', $websiteId));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getOrderStatuesAllowedForSync($websiteId = null)
    {
        return explode(',',
            $this->getWebsiteData('order/allowed_order_statuses_to_be_added_to_sync_queue', $websiteId));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isOrderExportStatusFilteringBeforeQueueEnabled($websiteId = null)
    {
        return (bool)$this->getWebsiteData('order/order_export_status_filtering_for_sync_queue_enabled', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getOrderExportDisallowedCustomerGroups($websiteId = null)
    {
        return explode(',', $this->getWebsiteData('order/export_disallowed_customer_groups', $websiteId));
    }

    /**
     * @return bool
     */
    public function isScheduledOrdersExportEnabled()
    {
        return (bool)$this->getWebsiteData('order/enable_scheduled_order_export');
    }

    /**
     * @return bool
     */
    public function isScheduledCustomersExportEnabled()
    {
        return (bool)$this->getWebsiteData('customer/enable_scheduled_customer_export');
    }

    /**
     * @param $entityType
     *
     * @return int
     */
    public function getScheduledEntityExportDelayTime($entityType)
    {
        return (int)$this->getWebsiteData($entityType . '/scheduled_' . $entityType . '_export_delay_time');
    }

    /**
     * @param string $entityType
     *
     * @return array|bool
     */
    public function getScheduledEntityExportRunTimes($entityType)
    {
        $values = $this->getWebsiteData($entityType . '/scheduled_' . $entityType . '_export_start_times');
        $possiblePhraseValues = [SyncSchedule::CRON_EVERY_MINUTE, SyncSchedule::CRON_EVERY_HOUR];
        if (!in_array($values, $possiblePhraseValues)) {
            return array_map('trim', explode(',', $values));
        } else {
            return false;
        }
    }

    /**
     * @param $entityType
     *
     * @return array|bool
     */
    public function getScheduledEntityImportRunTimes($entityType)
    {
        $values = $this->getWebsiteData($entityType . '/scheduled_' . $entityType . '_import_start_times');
        $possiblePhraseValues = [SyncSchedule::CRON_EVERY_MINUTE, SyncSchedule::CRON_EVERY_HOUR];
        if (!in_array($values, $possiblePhraseValues)) {
            return array_map('trim', explode(',', $values));
        } else {
            return false;
        }
    }

    /**
     * @param string $entityType
     *
     * @return bool
     */
    public function isScheduledEntityImportEnabled($entityType)
    {
        return (bool)$this->getWebsiteData($entityType . '/enable_scheduled_' . $entityType . '_import');
    }

    /**
     * @param string $entityType
     *
     * @return int
     */
    public function getScheduledEntityImportDelayTime($entityType)
    {
        return (int)$this->getWebsiteData($entityType . '/scheduled_' . $entityType . '_import_delay_time');
    }

    /**
     * @param string $type
     * @param string $lastProcessingTime
     *
     * @return bool|string
     */
    public function canExportEntityType($type, $lastProcessingTime)
    {
        $currentTime = time();
        $scheduledMode = false;

        if ($type == OrderModel::CODE && $this->isScheduledOrdersExportEnabled()) {
            $scheduledMode = true;
        }
        if ($type == CustomerModel::CODE && $this->isScheduledCustomersExportEnabled()) {
            $scheduledMode = true;
        }

        $isProcessingAllowed = !$scheduledMode;
        $lastProcessingTime = !$lastProcessingTime ? strtotime('12:00 AM') : $lastProcessingTime;

        if ($scheduledMode && $lastProcessingTime && $this->getScheduledEntityExportDelayTime($type) > 0
            && ($currentTime - $lastProcessingTime) < $this->getScheduledEntityExportDelayTime($type)
        ) {

            return $isProcessingAllowed;
        }

        if ($scheduledMode && $runTimes = $this->getScheduledEntityExportRunTimes($type)) {
            foreach ($runTimes as $strTime) {
                $scheduledTime = strtotime($strTime);
                if ($currentTime >= $scheduledTime && $scheduledTime > $lastProcessingTime) {
                    $isProcessingAllowed = true;
                    break;
                }
            }
        }

        return $isProcessingAllowed;
    }

    /**
     * @param string $type
     * @param string $lastProcessingTime
     *
     * @return bool|string
     */
    public function canImportEntityType($type, $lastProcessingTime)
    {
        $currentTime = time();
        $scheduledMode = false;

        if ($this->isScheduledEntityImportEnabled($type)) {
            $scheduledMode = true;
        }

        $isProcessingAllowed = !$scheduledMode;
        $lastProcessingTime = !$lastProcessingTime ? strtotime('12:00 AM') : $lastProcessingTime;

        if ($scheduledMode && $lastProcessingTime && $this->getScheduledEntityImportDelayTime($type) > 0
            && ($currentTime - $lastProcessingTime) < $this->getScheduledEntityImportDelayTime($type)
        ) {

            return $isProcessingAllowed;
        }

        if ($scheduledMode && $runTimes = $this->getScheduledEntityImportRunTimes($type)) {
            foreach ($runTimes as $strTime) {
                $scheduledTime = strtotime($strTime);
                if ($currentTime >= $scheduledTime && $scheduledTime > $lastProcessingTime) {
                    $isProcessingAllowed = true;
                    break;
                }
            }
        }

        return $isProcessingAllowed;
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getNAVReportsCustomerGroups($websiteId = null)
    {
        return explode(',', $this->getWebsiteData('customer/nav_reports_allowed_customer_groups', $websiteId));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getDefaultRmaStatus($websiteId = null)
    {
        return explode(',', $this->getWebsiteData('rma/default_rma_status', $websiteId));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getPriceRuleDisallowedCustomerGroups($websiteId = null)
    {
        return explode(',', $this->getWebsiteData('price_rule/disallowed_customer_groups', $websiteId));
    }

    /**
     * Display regular price
     *
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isDisplayRegularPrice($websiteId = null)
    {
        return (bool)$this->getWebsiteData('price_rule/display_regular_price', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isErrorEmailingEnabled($websiteId = null)
    {
        return (bool)$this->getWebsiteData('nav_connection/send_error_emails', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getErrorEmailSender($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/error_email_sender', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return array
     */
    public function getErrorRecipients($websiteId = null)
    {
        return array_map(
            'trim',
            explode(',', $this->getWebsiteData('nav_connection/error_email_recipient', $websiteId))
        );
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getErrorEmailTemplate($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/error_email_template', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getRetryOrderErrorEmailTemplate($websiteId = null)
    {
        return $this->getWebsiteData('nav_connection/retry_error_orders_email_template', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isNewCustomerPasswordResetEmailingEnabled($websiteId = null)
    {
        return (bool)$this->getWebsiteData('customer/send_new_customer_emails', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getNewCustomerPasswordResetEmailSender($websiteId = null)
    {
        return $this->getWebsiteData('customer/new_customer_email_sender', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return string
     */
    public function getNewCustomerPasswordResetEmailTemplate($websiteId = null)
    {
        return $this->getWebsiteData('customer/new_customer_email_template', $websiteId);
    }

    public function getFlag($path, $store = null)
    {
        return boolval($this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        ));
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isProductInStockStatusMandatory($websiteId = null)
    {
        return (bool)$this->getWebsiteData('product/set_in_stock', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isInventoryInStockStatusMandatory($websiteId = null)
    {
        return (bool)$this->getWebsiteData('inventory/set_in_stock', $websiteId);
    }

    public function isInventoryUpdatePrice($websiteId = null): bool
    {
        return (bool)$this->getWebsiteData('inventory/update_price', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isDisableNewProducts($websiteId = null)
    {
        return (bool)$this->getWebsiteData('product/disable_new_products', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isAssignProductToAllWebsites($websiteId = null)
    {
        return (bool)$this->getWebsiteData('product/is_assign_to_all_websites', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function isTierPriceLogicEnabled($websiteId = null)
    {
        return (bool)$this->getWebsiteData('tier_price/is_enabled', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return bool
     */
    public function canCreateShipmentWithNoTracking($websiteId = null)
    {
        return (bool)$this->getWebsiteData('shipment/allow_shipment_without_tracking_number', $websiteId);
    }

    /**
     * Get Malibu Mconnect config value per store
     *
     * @param string          $path
     * @param null|string|int $store
     *
     * @return mixed
     */
    public function get($path, $store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get Malibu Mconnect config value per website
     *
     * @param string          $path
     * @param null|string|int $website
     *
     * @return mixed
     */
    public function getWebsiteData($path, $website = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONFIG_SECTION . '/' . $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            $website
        );
    }

    /**
     * Get Global config value per store
     *
     * @param string          $path
     * @param null|string|int $store
     *
     * @return mixed
     */
    public function getConfigValue($path, $store = null)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }
}
