<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection\Soap;

use SoapClient;

class Client extends SoapClient
{
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $mConnectConfig = $objectManager->create('\MalibuCommerce\MConnect\Model\Config');
        $mConnectMailer = $objectManager->create('\MalibuCommerce\MConnect\Helper\Mail');

        $username = $mConnectConfig->getNavConnectionUsername();
        $password = $mConnectConfig->getNavConnectionPassword();
        $ch = curl_init($location);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Method: POST',
            'Connection: Keep-Alive',
            'User-Agent: PHP-SOAP-CURL',
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $action . '"',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($mConnectConfig->getIsInsecureConnectionAllowed()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($mConnectConfig->getUseNtlmAuthentication()) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        }
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_TIMEOUT, $mConnectConfig->getConnectionTimeout());

        try {
            $response = curl_exec($ch);
        } catch (\Exception $e) {
            curl_close($ch);
            if ($mConnectConfig->get('nav_connection/log')) {
                $this->logRequest($request, $location, $action, 500, null, 'Error: ' . $e->getMessage());
            }

            $request = [
                'Time'        => date('r'),
                'Location'    => $location,
                'PID'         => getmypid(),
                'Action'      => $action,
                'Body'        => is_array($request) ? print_r($request, true) : $request,
                'Request XML' => $this->decodeRequest('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $request),
            ];
            $mConnectMailer->sendErrorEmail('An error occurred when connecting to Navision.', $request, $e->getMessage());

            throw $e;
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header = substr($response, 0, $headerSize);
        $body = trim(substr($response, $headerSize));
        curl_close($ch);
        if ($mConnectConfig->get('nav_connection/log')) {
            $this->logRequest($request, $location, $action, $code, $header, $body);
        }

        return $body;
    }

    public function logRequest($request, $location, $action, $code, $header, $body)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $registry = $objectManager->get('\Magento\Framework\Registry');
        $queueId = $registry->registry('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE');
        $helper = $objectManager->get('\MalibuCommerce\MConnect\Helper\Data');
        $logFile = $helper->getLogFile($queueId, true, true);
        $writer = new \Zend\Log\Writer\Stream($logFile);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $request = [
            'Time'        => date('r'),
            'Location'    => $location,
            'PID'         => getmypid(),
            'Action'      => $action,
            'Body'        => is_array($request) ? print_r($request, true) : $request,
            'Request XML' => $this->decodeRequest('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $request),
        ];
        $response = [
            'Code'         => $code,
            'Headers'      => $header,
            'Body'         => $body,
            'Response XML' => $this->decodeRequest('/<responseXML>(.*)<\/responseXML>/', $body)
        ];
        $logger->debug('Debug Data', array(
            'Request'  => $request,
            'Response' => $response
        ));
    }

    public function decodeRequest($pattern, $value)
    {
        if (is_string($value) && preg_match($pattern, $value, $matches)
            && isset($matches[1]) && preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $matches[1])
        ) {
            return base64_decode($matches[1]);
        }

        return false;
    }
}
