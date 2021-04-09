<?php

namespace MalibuCommerce\MConnect\Model\Navision\Statement;

use MalibuCommerce\MConnect\Model\Navision\AbstractModel;

class Pdf extends AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        return false;
    }

    public function get($customerNumber, $startDate, $endDate)
    {
        $xml = new \simpleXMLElement('<customer_statement />');
        $params = $xml->addChild('parameters');
        $params->customer_number = $customerNumber;
        $params->start_date = $startDate;
        $params->end_date = $endDate;

        $this->getConnection()->setCallerModel($this);

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
