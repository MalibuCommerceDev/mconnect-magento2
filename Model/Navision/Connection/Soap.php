<?php
namespace MalibuCommerce\MConnect\Model\Navision\Connection;

use SoapClient;
use SoapFault;

class Soap
{
    const STREAM = '\MalibuCommerce\MConnect\Model\Navision\Connection\Stream';

    protected $_client;
    protected $_isStreamRegistered = false;
    protected $_restoreStream = false;
    protected $_scheme;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client
     */
    protected $mConnectNavisionConnectionSoapClient;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $mConnectHelper;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Helper\Data $mConnectHelper,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Helper\Data $mConnectHelper,
        \MalibuCommerce\MConnect\Model\Navision\Connection\Stream $stream,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    )
    {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectHelper = $mConnectHelper;
        $this->_streamRegister();
        $this->_client = new \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client(
            $stream->stream_open($this->mConnectConfig->getNavConnectionUrl(), null, null, $mConnectConfig),
            array(
                'cache_wsdl'         => 0,
                'connection_timeout' => 10,
                'trace' => 1,
            )
        );
    }

    public function __call($method, $arguments)
    {
        $this->_streamRegister();

        try {
            $result = call_user_func_array(array($this->_client, $method), $arguments);
        } catch (SoapFault $e) {
            if ($this->mConnectConfig->get('navision/log')) {
                $this->_client->logRequest($arguments, null, $method, null, null, $e->getMessage());
            }
            $this->mConnectHelper->sendErrorEmail(array(
                'title'    => 'An unknown error occured when connecting to Navision.',
                'body'     => 'Action: ' . $method,
                'response' => $e->getMessage(),
            ));
            echo 'SoapFault in Soap::__call : ';
            throw $e;
        }
        $this->_streamUnregister();
        return $result;
    }

    protected function _streamRegister()
    {

        if ($this->_isStreamRegistered) {
            return;
        }
        $scheme = $this->_getScheme();
        if (in_array($scheme, stream_get_wrappers())) {
            $this->_restoreStream = true;
            if (!stream_wrapper_unregister($scheme)) {
                throw new \LogicException(sprintf('Failed to unregister "%s" stream when connecting to Navision.', $scheme));
            }
        }
        if (!stream_wrapper_register($scheme, self::STREAM)) {
            throw new \LogicException(sprintf('Failed to register "%s" stream when connecting to Navision.', $scheme));
        }
        $this->_isStreamRegistered = true;
    }

    protected function _streamUnregister()
    {
        if (!$this->_isStreamRegistered) {
            return;
        }
        if ($this->_restoreStream) {
            stream_wrapper_restore($this->_getScheme());
        }
        $this->_isStreamRegistered = false;
    }

    protected function _getScheme()
    {
        if ($this->_scheme === null) {
            $config = $this->mConnectConfig;
            $components = parse_url($config->getNavConnectionUrl());
            if (!isset($components['scheme'])) {
                throw new \LogicException('Failed to parse scheme from Navision URL. Please check your system configuration.');
            }
            $this->_scheme = $components['scheme'];
        }
        return $this->_scheme;
    }
}
