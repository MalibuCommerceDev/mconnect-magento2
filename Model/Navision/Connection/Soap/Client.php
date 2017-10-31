<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection\Soap;

use SoapClient;

class Client extends SoapClient
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $mConnectHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /*public function __construct(
        $wsdl,
        $options = array(),
        $mConnectConfig,
        $mConnectHelper,
        $logger,
        $directoryList
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectHelper = $mConnectHelper;
        $this->logger = $logger;
        $this->directoryList = $directoryList;

        parent::__construct(
            $wsdl,
            $options
        );
    }*/


    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $mConnectConfig = $objectManager->create('\MalibuCommerce\MConnect\Model\Config');
        $mConnectHelper = $objectManager->create('\MalibuCommerce\MConnect\Helper\Data');

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        try {
            $response = curl_exec($ch);
        } catch (Exception $e) {
            curl_close($ch);
            $mConnectHelper->sendErrorEmail(array(
                'title'    => 'An unknown error occured when connecting to Navision.',
                'body'     => 'Action: ' . $action,
                'response' => $e->getMessage(),
            ));
            throw $e;
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header     = substr($response, 0, $headerSize);
        $body       = trim(substr($response, $headerSize));
        curl_close($ch);
        if ($mConnectConfig->get('navision/log')) {
            $this->logRequest($request, $location, $action, $code, $header, $body);
        }
        return $body;
    }

    public function logRequest($request, $location, $action, $code, $header, $body)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $logger = $objectManager->create('\Psr\Log\LoggerInterface');

        $logger->log(\Monolog\Logger::DEBUG, array('request'=>$this->_decodeBase64('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $request),'location'=>$location,'action'=>$action,'code'=>$code,'header'=>$this->_parseHeader($header),'body'=>$this->_decodeBase64('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $body)));
    }

    public static function getLogFile($id, $absolute = true)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $directoryList = $objectManager->create('\Magento\Framework\App\Filesystem\DirectoryList');

        $dir = 'malibucommerce_mconnect';
        if ($id) {
            $file = 'queue_' . $id . '.log';
        } else {
            $file = 'navision_soap.log';
        }
        $logDirObj = $directoryList;
        $logDir = $logDirObj->getPath('log') . DS . $dir;
        if (!is_dir($logDir)) {
            mkdir($logDir);
            chmod($logDir, 0750);
        }
        if ($absolute) {
            return $logDir . DS . $file;
        }
        return $dir . DS . $file;
    }

    protected function _parseHeader($rawData)
    {
        if ($rawData === null) {
            return $rawData;
        }
        $data = [];
        foreach (explode("\n", trim($rawData)) as $line) {
            $bits = explode(': ', $line);
            if (count($bits) > 1) {
                $key = $bits[0];
                unset($bits[0]);
                $data[$key] = trim(implode(': ', $bits));
            }
        }
        return $data;
    }

    protected function _decodeBase64($pattern, $value)
    {
        if (preg_match($pattern, $value, $matches) && isset($matches[1]) && preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $matches[1])) {
            return base64_decode($matches[1]);
        }
        return false;
    }
}
