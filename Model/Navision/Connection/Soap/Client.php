<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection\Soap;

use SoapClient;

class Client extends SoapClient
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $mConnectHelper;

    /**
     * @var int
     */
    protected $websiteId = 0;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Helper\Data $mConnectHelper,
        $wsdl,
        array $options = null
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectHelper = $mConnectHelper;

        parent::__construct($wsdl, $options);
    }

    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $websiteId;
    }

    public function getWebsiteId()
    {
        return $this->websiteId;
    }

    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int    $version
     * @param int    $one_way
     *
     * @return string
     * @throws \Throwable
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $username = $this->mConnectConfig->getNavConnectionUsername($this->getWebsiteId());
        $password = $this->mConnectConfig->getNavConnectionPassword($this->getWebsiteId());
        $ch = curl_init($location);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Method: POST',
            'Connection: Keep-Alive',
            'User-Agent: PHP-SOAP-CURL',
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $action . '"',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($this->mConnectConfig->getIsInsecureConnectionAllowed($this->getWebsiteId())) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($method = $this->mConnectConfig->getAuthenticationMethod($this->getWebsiteId())) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, $method);
        }

        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->mConnectConfig->getConnectionTimeout($this->getWebsiteId()));

        try {
            $response = curl_exec($ch);
        } catch (\Throwable $e) {
            curl_close($ch);
            throw $e;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header = substr($response, 0, $headerSize);
        $body = trim(substr($response, $headerSize));
        curl_close($ch);

        $this->mConnectHelper->logRequest($request, $location, $action, $code, $header, $body);

        return $body;
    }
}
