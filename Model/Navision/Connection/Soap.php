<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection;

class Soap
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client
     */
    protected $soapClient;
    protected $isStreamRegistered = false;
    protected $restoreStream      = false;
    protected $protocol;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client
     */
    protected $mConnectNavisionConnectionSoapClient;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Mail
     */
    protected $mConnectMailer;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $mConnectHelper;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Stream
     */
    protected $stream;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Helper\Mail $mConnectMailer,
        \MalibuCommerce\MConnect\Helper\Data $mConnectHelper,
        \MalibuCommerce\MConnect\Model\Navision\Connection\Stream $stream,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectMailer = $mConnectMailer;
        $this->mConnectHelper = $mConnectHelper;
        $this->stream = $stream;
    }

    public function __call($method, $arguments)
    {
        try {
            $websiteId = $arguments['website_id'] ?? 0;

            $this->registerStream($websiteId);
            $this->registerClient($websiteId);

            $this->soapClient->setWebsiteId($websiteId);
            $this->stream->setWebsiteId($websiteId);

            unset($arguments['website_id']);
            $result = call_user_func_array(array($this->soapClient, $method), $arguments);
        } catch (\Throwable $e) {
            $this->mConnectHelper->logRequestError($arguments, null, $method, $e);

            throw $e;
        }
        $this->unregisterStream($websiteId);

        return $result;
    }

    protected function registerStream($websiteId = 0)
    {
        if ($this->isStreamRegistered) {
            return;
        }
        $protocol = $this->getProtocol($websiteId);
        if (in_array($protocol, stream_get_wrappers())) {
            $this->restoreStream = true;
            if (!stream_wrapper_unregister($protocol)) {
                throw new \LogicException(sprintf('Failed to unregister "%s" stream when connecting to Navision.', $protocol));
            }
        }
        if (!stream_wrapper_register($protocol, \MalibuCommerce\MConnect\Model\Navision\Connection\Stream::class)) {
            throw new \LogicException(sprintf('Failed to register "%s" stream when connecting to Navision.', $protocol));
        }
        $this->isStreamRegistered = true;
    }

    protected function registerClient($websiteId = 0)
    {
        if ($this->soapClient === null) {
            $this->soapClient = new \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client(
                $this->mConnectConfig,
                $this->mConnectHelper,
                $this->stream->stream_open($this->mConnectConfig->getNavConnectionUrl($websiteId)),
                [
                    'cache_wsdl'         => 0,
                    'connection_timeout' => $this->mConnectConfig->getConnectionTimeout($websiteId),
                    'trace'              => 1,
                    'exceptions'         => true,
                ]
            );
        }

        return $this;
    }

    protected function unregisterStream($websiteId = 0)
    {
        if (!$this->isStreamRegistered) {
            return;
        }
        if ($this->restoreStream) {
            stream_wrapper_restore($this->getProtocol($websiteId));
        }
        $this->isStreamRegistered = false;
    }

    protected function getProtocol($websiteId = 0)
    {
        if ($this->protocol === null) {
            $config = $this->mConnectConfig;
            $components = parse_url($config->getNavConnectionUrl($websiteId));
            if (!isset($components['scheme'])) {
                throw new \LogicException('Failed to parse scheme from Navision URL. Please check your system configuration.');
            }
            $this->protocol = $components['scheme'];
        }

        return $this->protocol;
    }
}
