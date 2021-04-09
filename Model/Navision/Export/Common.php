<?php

namespace MalibuCommerce\MConnect\Model\Navision\Export;

use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Model\Navision\AbstractModel;
use MalibuCommerce\MConnect\Model\Navision\Connection;

class Common extends AbstractModel
{
    protected $_rootNode;
    protected $_listNode;

    /**
     * @var Connection
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
     * Common constructor.
     *
     * @param Config              $config
     * @param Connection $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                           $logger
     * @param array                                              $data
     */
    public function __construct(
        Config $config,
        Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->config = $config;
        $this->mConnectNavisionConnection = $mConnectNavisionConnection;
        $this->logger = $logger;

        parent::__construct($config, $mConnectNavisionConnection, $logger, $data);
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        return false;
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

        $this->mConnectNavisionConnection->setCallerModel($this);

        $response = $this->mConnectNavisionConnection->ExportList([
            'requestXML'  => base64_encode($xml->asXML()),
            'responseXML' => false,
            'errorLogXML' => false,
        ]);
        $this->handleErrorLogXml($response);
        if (!$response || !isset($response->responseXML) || !strlen($response->responseXML)) {

            return false;
        }
        $xml = new \simpleXMLElement(base64_decode($response->responseXML));
        $entities = [];
        foreach ($xml->{$this->_listNode} as $entity) {
            $data = [];
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
