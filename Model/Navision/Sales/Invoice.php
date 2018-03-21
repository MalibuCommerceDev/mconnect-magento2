<?php

namespace MalibuCommerce\MConnect\Model\Navision\Sales;

class Invoice extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    protected $_rootNode = 'sales_invoice_list';

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
        $xmlResponse = false;
        if ($response->responseXML) {
            $xmlResponse = new \simpleXMLElement(base64_decode($response->responseXML));
        } elseif ($response->errorLogXML) {
            $xmlResponse = new \simpleXMLElement(base64_decode($response->errorLogXML));
        }
        return $xmlResponse;
    }
}
