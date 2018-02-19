<?php
namespace MalibuCommerce\MConnect\Model\Navision;

class AbstractModel extends \Magento\Framework\DataObject
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection
     */
    protected $mConnectNavisionConnection;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        array $data = []
    ) {
        $this->mConnectNavisionConnection = $mConnectNavisionConnection;
        parent::__construct(
            $data
        );
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
        return $this->_prepareResponseXml(
            $this->_doRequest(
                'export',
                $this->_prepareRequestXml($action, $parameters)
            )
        );
    }

    protected function _import($action, $parameters = array())
    {
        return $this->_prepareResponseXml(
            $this->_doRequest(
                'import',
                $this->_prepareRequestXml($action, $parameters)
            )
        );
    }

    protected function _prepareRequestXml($action, $parameters = array())
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

    protected function _prepareResponseXml($response)
    {
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
                throw new \Magento\Framework\Exception\LocalizedException('Unsupported request type');
        }

        return $this->getConnection()->$method(array(
            'requestXML'  => $xml,
            'responseXML' => false,
        ));
    }
}
