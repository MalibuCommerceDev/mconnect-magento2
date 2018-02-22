<?php
namespace MalibuCommerce\MConnect\Model;


class Config
{
    const XML_PATH_CONFIG_SECTION = 'malibucommerce_mconnect';
    const DEFAULT_NAV_CONNECTION_TIMEOUT = 10;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Registry $registry
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_encryptor = $encryptor;
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

    public function getTriggerPassword($store = null)
    {
        $password = $this->scopeConfig->getValue(self::XML_PATH_CONFIG_SECTION . '/' . 'nav_connection/trigger_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
        $decryptedPassword = $this->_encryptor->decrypt($password);
        return $decryptedPassword;
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
}
