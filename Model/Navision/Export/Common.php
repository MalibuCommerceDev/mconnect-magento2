<?php

namespace MalibuCommerce\MConnect\Model\Navision\Export;

class Common extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    protected $_rootNode;
    protected $_listNode;

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
     * Common constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\Config              $config
     * @param \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param array                                              $data
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

        parent::__construct($config, $mConnectNavisionConnection, $logger, $data);
    }

    /**
     * Make request to Nav system and return entities
     *
     * @param $options
     *
     * @return array|bool
     * @throws \Exception
     */
    public function get($options)
    {
        $xml = new \simpleXMLElement('<' . $this->_rootNode . ' />');
        $params = $xml->addChild('parameters');
        foreach ($options as $field => $value) {
            $params->$field = $value;
        }
        $response = $this->mConnectNavisionConnection->ExportList(array(
            'requestXML'  => base64_encode($xml->asXML()),
            'responseXML' => false,
            'errorLogXML' => false,
        ));
        $this->handleErrorLogXml($response);
        if (!$response || (!isset($response->responseXML) && !strlen($response->responseXML))) {
            return false;
        }
        $xml = new \simpleXMLElement(base64_decode($response->responseXML));
        $entities = array();
        foreach ($xml->{$this->_listNode} as $entity) {
            $data = array();
            foreach ($entity as $attr => $value) {
                $pieces = preg_split('/(?=[A-Z])/', $attr);
                foreach ($pieces as &$piece) {
                    $piece = strtolower($piece);
                }
                $data[implode('_', $pieces)] = (string)$value;
            }
            $dataObject = new \Magento\Framework\DataObject();
            $dataObject->setData($data);
            $entities[] = $dataObject;
        }

        return $entities;
    }
}
