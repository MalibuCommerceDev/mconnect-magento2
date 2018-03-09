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

    protected function _export($action, $parameters = array())
    {
        return $this->prepareResponseXml(
            $this->_doRequest(
                'export',
                $this->prepareRequestXml($action, $parameters)
            )
        );
    }

    protected function _import($action, $parameters = array())
    {
        return $this->prepareResponseXml(
            $this->_doRequest(
                'import',
                $this->prepareRequestXml($action, $parameters)
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
            throw new \Magento\Framework\Exception\LocalizedException(__('Server response is invalid'));
        }
        $xml = base64_decode($response->responseXML);

        return new \simpleXMLElement($xml);
    }

    protected function _doRequest($type, $xml)
    {
        switch ($type) {
            case 'export':
                $method = 'ExportFromNAV';
                break;
            case 'import':
                $method = 'ImportToNAV';
                break;
            default:
                throw new \Magento\Framework\Exception\LocalizedException(__(sprintf('Unsupported request type "%s"', $type)));
        }

        return $this->getConnection()->$method(array(
            'requestXML'  => $xml,
            'responseXML' => false,
        ));
    }
}
