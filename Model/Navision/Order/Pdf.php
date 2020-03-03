<?php

namespace MalibuCommerce\MConnect\Model\Navision\Order;

class Pdf extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        return false;
    }

    /**
     * @param $orderNumber
     * @param $customerNumber
     *
     * @return bool|string
     */
    public function get($orderNumber, $customerNumber)
    {
        $xml = new \simpleXMLElement('<sales_order />');
        $params = $xml->addChild('parameters');
        $params->order_number = $orderNumber;
        $params->customer_number = $customerNumber;
        $response = $this->getConnection()->ExportPDF([
            'requestXML'  => base64_encode($xml->asXML()),
            'response'    => false,
            'errorLogXML' => false,
        ]);
        if (isset($response->response) && strlen($response->response)) {
            $responsePdf = base64_decode($response->response);

            return $responsePdf;
        }

        return false;
    }
}
