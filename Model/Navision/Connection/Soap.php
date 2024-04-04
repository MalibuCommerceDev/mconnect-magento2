<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection;

use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Helper\Mail;
use MalibuCommerce\MConnect\Helper\Data;
use MalibuCommerce\MConnect\Model\Navision\AbstractModel;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Soap
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client[]
     */
    protected array $soapClients = [];

    protected bool $isStreamRegistered = false;
    protected bool $restoreStream = false;
    protected ?string $protocol = null;

    protected Config $mConnectConfig;
    protected Mail $mConnectMailer;
    protected Data $mConnectHelper;
    protected Stream $stream;
    protected AbstractModel $callerModel;

    public function __construct(
        Config $mConnectConfig,
        Mail $mConnectMailer,
        Data $mConnectHelper,
        Stream $stream,
        LoggerInterface $logger,
        DirectoryList $directoryList
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectMailer = $mConnectMailer;
        $this->mConnectHelper = $mConnectHelper;
        $this->stream = $stream;
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Throwable
     */
    public function __call($method, $arguments)
    {
        $mt = microtime(true);
        try {
            $websiteId = $arguments[0]['website_id'] ?? 0;

            $this->registerStream($websiteId);
            $this->registerClient($websiteId);

            unset($arguments['website_id']);
            $result = call_user_func_array([$this->soapClients[$websiteId], $method], $arguments);

            $this->mConnectHelper->logSoapRequestResponse(
                $this->soapClients[$websiteId]->__getLastRequest(),
                $this->soapClients[$websiteId]->__getLastResponse(),
                ['duration' => microtime(true) - $mt]
            );
        } catch (\Throwable $e) {
            try {
                if (!empty($this->soapClients[$websiteId])) {
                    $this->mConnectHelper->logSoapRequestResponse(
                        $this->soapClients[$websiteId]->__getLastRequest(),
                        $this->soapClients[$websiteId]->__getLastResponse(),
                        ['duration' => microtime(true) - $mt]
                    );
                }
            } catch (\Throwable $e) {

            }

            $this->mConnectHelper->logRequestError(
                $arguments,
                $this->mConnectConfig->getNavConnectionUrl($websiteId),
                $method,
                $e
            );

            $this->unregisterStream($websiteId);
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
        if (!stream_wrapper_register($protocol, Stream::class)) {
            throw new \LogicException(sprintf('Failed to register "%s" stream when connecting to Navision.', $protocol));
        }
        $this->isStreamRegistered = true;
        $this->stream->setWebsiteId($websiteId);
        $this->stream->setCallerModel($this->getCallerModel());
    }

    protected function registerClient($websiteId = 0)
    {
        if (!array_key_exists($websiteId, $this->soapClients)) {
            $this->soapClients[$websiteId] = new \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client(
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

        $this->soapClients[$websiteId]->setWebsiteId($websiteId);
        $this->soapClients[$websiteId]->setCallerModel($this->getCallerModel());

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

    /**
     * @param AbstractModel $model
     *
     * @return $this
     */
    public function setCallerModel(AbstractModel $model)
    {
        $this->callerModel = $model;

        return $this;
    }

    /**
     * @return AbstractModel
     */
    public function getCallerModel()
    {
        return $this->callerModel;
    }
}
