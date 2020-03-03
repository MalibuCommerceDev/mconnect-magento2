<?php

namespace MalibuCommerce\MConnect\Model\Navision\Invoice;

class Pdf extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        return false;
    }

    public function get($invoiceNumber, $customerNumber)
    {
        $xml = new \simpleXMLElement('<sales_invoice />');
        $params = $xml->addChild('parameters');
        $params->invoice_number = $invoiceNumber;
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
