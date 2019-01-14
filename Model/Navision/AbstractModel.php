<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class AbstractModel extends \Magento\Framework\DataObject
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
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * AbstractModel constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\Config $config
     * @param Connection                            $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface              $logger
     * @param array                                 $data
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->config = $config;
        $this->mConnectNavisionConnection = $mConnectNavisionConnection;
        $this->logger = $logger;

        parent::__construct($data);
    }

    /**
     * @return \MalibuCommerce\MConnect\Model\Navision\Connection
     */
    public function getConnection()
    {
        return $this->mConnectNavisionConnection;
    }

    protected function _export($action, $parameters = array(), $websiteId = 0)
    {
        static $attempts = 1;

        try {
            $responseXml = $this->doRequest('export', $this->prepareRequestXml($action, $parameters),  $websiteId);
        } catch (\Throwable $e) {
            if (!$this->config->getWebsiteData('nav_connection/retry_on_failure', $websiteId)) {
                $attempts = 1;
                throw $e;
            }

            $maxAttempts = (int)$this->config->getWebsiteData('nav_connection/retry_max_count', $websiteId);
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

        throw new \RuntimeException('Empty Response from NAV server, try to increase NAV connection timeout in Magento Admin Panel -> Stores -> Configuration -> Services -> Malibu Connect -> NAV Connection');
    }

    protected function _import($action, $parameters = array(), $websiteId = 0)
    {
        return $this->prepareResponseXml(
            $this->doRequest(
                'import',
                $this->prepareRequestXml($action, $parameters),
                $websiteId
            )
        );
    }

    protected function prepareRequestXml($action, $parameters = array())
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
            case 'export':
                $method = 'ExportFromNAV';
                break;
            case 'import':
                $method = 'ImportToNAV';
                break;
            default:
                throw new \RuntimeException(sprintf('Unsupported request type "%s"', $type));
        }

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
                $errors = array();
                foreach ($xml->Error as $error) {
                    $errors[] = (string) $error;
                }
                throw new \LogicException(implode(',', $errors));
            }
        }
    }
}
