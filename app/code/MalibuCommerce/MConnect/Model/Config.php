<?php
namespace MalibuCommerce\MConnect\Model;


class Config
{
    const XML_PATH_CONFIG_SECTION = 'malibucommerce_mconnect';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Registry $registry
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
    }
    public function getFlag($data, $store = null)
    {
        return boolval($this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . $data, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store));
    }

    public function get($data, $store = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . $data, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
    }

    public function isModuleEnabled()
    {
        return $this->getFlag('general/enabled');
    }

    public function getErrorRecipients()
    {
        return array_map('trim', explode(',', $this->get('navision/error_recipient')));
    }

    public function getNavConnectionId($store = null)
    {
        return 1;
        /*return $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);*/
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
}
