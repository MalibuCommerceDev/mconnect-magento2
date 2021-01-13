<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use MalibuCommerce\MConnect\Model\Config;

abstract class AbstractModel extends \Magento\Framework\DataObject
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection
     */
    protected $mConnectNavisionConnection;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * AbstractModel constructor.
     *
     * @param Config $config
     * @param Connection                            $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface              $logger
     * @param array                                 $data
     */
    public function __construct(
        Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->config = $config;
        $this->logger = $logger;

        $this->mConnectNavisionConnection = $mConnectNavisionConnection;

        parent::__construct($data);
    }

    /**
     * @param int  $page
     * @param bool $lastUpdated
     * @param int  $websiteId
     *
     * @return \simpleXMLElement
     */
    abstract public function export($page = 0, $lastUpdated = false, $websiteId = 0);

    /**
     * @return \MalibuCommerce\MConnect\Model\Navision\Connection
     */
    public function getConnection()
    {
        return $this->mConnectNavisionConnection;
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    protected function isRetryOnFailureEnabled($websiteId = 0)
    {
        return (bool)$this->config->getWebsiteData('nav_connection/retry_on_failure', $websiteId);
    }

    /**
     * @param int $websiteId
     *
     * @return int
     */
    protected function getRetryAttemptsCount($websiteId = 0)
    {
        return (int)$this->config->getWebsiteData('nav_connection/retry_max_count', $websiteId);
    }

    /**
     * @param null|int|string|\Magento\Store\Model\Website $websiteId
     *
     * @return int
     */
    public function getConnectionTimeout($websiteId = null)
    {
        $timeout = (int)$this->config->getWebsiteData('nav_connection/connection_timeout', $websiteId);
        if ($timeout <= 0) {
            return Config::DEFAULT_NAV_CONNECTION_TIMEOUT;
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
        $timeout = (int)$this->config->getWebsiteData('nav_connection/request_timeout', $websiteId);
        if ($timeout <= 0) {
            return Config::DEFAULT_NAV_REQUEST_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * @param string $action
     * @param array $parameters
     * @param int   $websiteId
     * @param false $retryOnFailure
     * @param int   $maxRetries
     *
     * @return \simpleXMLElement
     * @throws \Throwable
     */
    protected function _export($action, $parameters = [], $websiteId = 0)
    {
        static $attempts = 1;

        try {
            $responseXml = $this->doRequest(\MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT, $this->prepareRequestXml($action, $parameters), $websiteId);
        } catch (\Throwable $e) {
            if (!$this->isRetryOnFailureEnabled($websiteId)) {
                $attempts = 1;
                throw $e;
            }

            $maxAttempts = $this->getRetryAttemptsCount($websiteId);
            if ($attempts <= $maxAttempts) {
                sleep(pow(2, $attempts));
                $attempts++;

                return $this->_export($action, $parameters, $websiteId);
            }

            $attempts = 1;
            throw $e;
        }

        if (!empty($responseXml)) {
            $attempts = 1;

            return $this->prepareResponseXml($responseXml);
        }

        throw new \RuntimeException('Empty Response from NAV server, try to increase NAV connection timeout in Magento Admin Panel -> Stores -> Configuration -> Services -> M-Connect -> NAV Connection');
    }

    protected function _import($action, $parameters = [], $websiteId = 0)
    {
        return $this->prepareResponseXml(
            $this->doRequest(
                \MalibuCommerce\MConnect\Model\Queue::ACTION_IMPORT,
                $this->prepareRequestXml($action, $parameters),
                $websiteId
            )
        );
    }

    /**
     * @param string $action
     * @param array|\simpleXMLElement $parameters
     *
     * @return string
     */
    protected function prepareRequestXml($action, $parameters = [])
    {
        if ($parameters instanceof \simpleXMLElement) {
            $xml = $parameters;
        } elseif (count($parameters)) {
            $xml = new \simpleXMLElement(sprintf('<%s />', $action));
            $child = $xml->addChild('parameters');
            foreach ($parameters as $node => $value) {
                $child->$node = $value;
            }
        }

        $xml = $xml->asXML();

        return base64_encode($xml);
    }

    protected function prepareResponseXml($response)
    {
        if (!isset($response->responseXML)) {
            $this->logger->critical('Mconnect NAV error - Server response is invalid', ['response' => $response]);
            throw new \RuntimeException('Server response is invalid');
        }
        $xml = base64_decode($response->responseXML);

        return new \simpleXMLElement($xml);
    }

    protected function doRequest($type, $xml, $websiteId = 0)
    {
        switch ($type) {
            case \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT:
                $method = 'ExportFromNAV';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::ACTION_IMPORT:
                $method = 'ImportToNAV';
                break;
            default:
                throw new \RuntimeException(sprintf('Unsupported request type "%s"', $type));
        }

        $this->getConnection()->setCallerModel($this);

        return $this->getConnection()->$method([
            'requestXML'  => $xml,
            'responseXML' => false,
            'website_id'  => $websiteId
        ]);
    }

    /**
     * Handle ErrorLog in Xml response
     *
     * @param $response
     * @throws \Exception
     */
    protected function handleErrorLogXml($response)
    {
        if (isset($response->errorLogXML) && strlen($response->errorLogXML)) {
            $xml = new \simpleXMLElement(base64_decode($response->errorLogXML));
            if (isset($xml->Error) && (strlen($xml->Error) || count($xml->Error))) {
                $errors = [];
                foreach ($xml->Error as $error) {
                    $errors[] = (string) $error;
                }
                throw new \LogicException(implode(',', $errors));
            }
        }
    }
}
