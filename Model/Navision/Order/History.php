<?php

namespace MalibuCommerce\MConnect\Model\Navision\Order;

class History extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    protected $_rootNode = 'open_order_list';

    public function import($options)
    {
        $options = json_decode($options);
        $xml = new \simpleXMLElement('<' . $this->_rootNode . ' />');
        $params = $xml->addChild('parameters');
        foreach ($options as $field => $value) {
            $params->$field = $value;
        }

        $response = $this->getConnection()->ExportList(array(
            'requestXML' => base64_encode($xml->asXML()),
            'responseXML' => false,
            'errorLogXML' => false,
        ));
        $xmlResponse = new \simpleXMLElement(base64_decode($response->responseXML));
        return $xmlResponse;
    }
}