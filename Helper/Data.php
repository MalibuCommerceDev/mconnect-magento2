<?php

namespace MalibuCommerce\MConnect\Helper;

use \Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\App\ObjectManager;
use \Magento\Framework\Serialize\Serializer\Json;
use MalibuCommerce\MConnect\Model\Logger;
use MalibuCommerce\MConnect\Model\Queue;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const ALLOWED_LOG_SIZE_TO_BE_VIEWED = 16777200; // in bytes, ~16 MB
    const QUEUE_ITEM_MAX_MESSAGE_SIZE   = 16777200; // in bytes, ~16 MB

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Mail
     */
    protected $mConnectMailer;

    /**
     * @var \MalibuCommerce\MConnect\Model\ResourceModel\Queue
     */
    protected $queueResourceModel;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Serializer interface instance.
     *
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $salesOrderFactory;

    protected $logDataCache = [];

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\ResourceModel\Queue $queueResourceModel,
        \MalibuCommerce\MConnect\Helper\Mail $mConnectMailer,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Helper\Context $context,
        Logger $logger,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->queueResourceModel = $queueResourceModel;
        $this->mConnectMailer = $mConnectMailer;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->serializer = $serializer ? : ObjectManager::getInstance()->get(Json::class);

        parent::__construct($context);
    }

    /**
     * @param int  $queueItemId
     * @param bool $absolute
     * @param bool $nameOnly
     *
     * @return bool|mixed|string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getLog($queueItemId, $absolute = true, $nameOnly = false)
    {
        if ($this->isLogDataToDb()) {
            $data = $this->getLogFromDb($queueItemId);
            if (!empty($data)) {

                return $data;
            } else {

                return $this->getLogFile($queueItemId, $absolute, $nameOnly);
            }
        }

        $file = $this->getLogFile($queueItemId, $absolute, $nameOnly);
        if (!file_exists($file)) {
            return $this->getLogFromDb($queueItemId);
        }

        return $file;
    }

    public function getLogFromDb($queueItemId)
    {
        if (!array_key_exists($queueItemId, $this->logDataCache)) {
            $this->logDataCache[$queueItemId] = $this->queueResourceModel->getLog($queueItemId);
        }

        return $this->logDataCache[$queueItemId];
    }

    /**
     * @param int  $queueItemId
     * @param bool $absolute
     * @param bool $nameOnly
     *
     * @return bool|string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getLogFile($queueItemId, $absolute = true, $nameOnly = false)
    {
        $directoryList = new DirectoryList(BP);

        $dir = 'mconnect';
        if ($queueItemId) {
            $file = 'queue_' . $queueItemId . '.log';
        } else {
            $file = 'navision_soap.log';
        }
        $logDirObj = $directoryList;
        $logDir = $logDirObj->getPath('log');
        $logDir .= DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0770, true);
        }

        $file = ($absolute ? $logDir : $dir) . DIRECTORY_SEPARATOR . $file;

        return !file_exists($file) && !$nameOnly ? false : $file;
    }

    public function getLogSize($data, $humanReadable = true)
    {
        if (!is_file($data)) {
            $bytes = mb_strlen($data);
            if (!$humanReadable) {
                return $bytes;
            }

            return $this->getFormattedSize($bytes);
        }

        return $this->getFileSize($data, $humanReadable);
    }

    public function getFileSize($file, $humanReadable = true)
    {
        if (!file_exists($file)) {
            return false;
        }
        $bytes = filesize($file);
        if (!$humanReadable) {
            return $bytes;
        }

        return $this->getFormattedSize($bytes);
    }

    public function getFormattedSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return number_format($bytes, 2) . ' ' . $units[$pow];
    }

    public function getLogContents($queueItemId, $asString = true)
    {
        $dataLog = $this->getLog($queueItemId, true, true);
        if (!$dataLog) {

            return false;
        }

        $results = [];
        if (file_exists($dataLog)) {
            $contents = file_get_contents($dataLog);
            if (preg_match_all('~({.+})~', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $debug = json_decode($match);
                    $result = [];
                    foreach ($debug as $title => $data) {
                        if (preg_match('~({.+})~', $data, $matches2)) {
                            $data = json_decode($matches2[1]);
                        }
                        $result[$title] = $data;
                    }
                    $results[] = $result;
                }
            }
        } else {
            $contents = $dataLog;
            $results[] = $dataLog;
        }

        if (count($results)) {
            return $asString ? print_r($results, true) : $results;
        }

        return $contents;
    }

    /**
     * Render queue item status for HTML version
     *
     * @param string $status
     * @param string $message
     * @param string $finishedAt
     *
     * @return string
     */
    public function getQueueItemStatusHtml($status, $message, $finishedAt)
    {
        $result = '';
        $style = 'text-transform: uppercase;'
                 . ' font-weight: bold;'
                 . ' color: white;'
                 . ' font-size: 10px;'
                 . ' width: 100%;'
                 . ' display: block;'
                 . ' text-align: center;'
                 . ' padding: 3px;'
                 . ' border-radius: 10px;';
        $title = 'Finished At: ' . $finishedAt . "\n" . htmlentities((string)$message);
        $background = false;
        switch ($status) {
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING:
                $background = '#9a9a9a';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_RUNNING:
                $background = '#28dade';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS:
                $background = '#00c500';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_WARNING:
                $background = '#ff5e00';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_ERROR:
                $background = '#ff0000';
                break;
            default:
                $result = $status;
        }
        if ($background) {
            $result = '<span title="' . $title . '" style="' . $style . ' background: ' . $background . ';">' . $status . '</span>';
        }

        return $result;
    }

    /**
     * @param $request
     * @param $response
     *
     * @return bool|null
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function logSoapRequestResponse($request, $response, $debug = [])
    {
        if (!$this->mConnectConfig->getIsSoapDebugEnabled()) {

            return false;
        }

        $e = new \Exception();
        try {
            $debugData = [
                'time'     => date('c'),
                'request'  => str_ireplace('><', ">\n<", $request),
                'response' => str_ireplace('><', ">\n<", $response),
                'pid'      => getmypid(),
                'host'     => gethostname(),
                'trace'    => $e->getTraceAsString(),
            ];
            $debugData += $debug;
            $directoryList = new DirectoryList(BP);
            $logFile = $directoryList->getPath('log') . DIRECTORY_SEPARATOR . 'malibu_connect_soap.log';
            file_put_contents($logFile, print_r($debugData, true), FILE_APPEND);
        } catch (\Throwable $e) {

            return null;
        }

        return true;
    }

    /**
     * @param array|string $request
     * @param string       $navUrl
     * @param string       $action
     * @param \Throwable   $e
     *
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function logRequestError($request, $navUrl, $action, \Throwable $e)
    {
        try {
            if (!$this->mConnectConfig->get('nav_connection/log')) {

                return false;
            }

            if (is_array($request) && !empty($request[0]['requestXML'])) {
                $request = $request[0]['requestXML'];
            }

            $this->logRequest(
                $request,
                $navUrl,
                $action,
                500,
                null,
                'Error: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString()
            );

            $request = $this->prepareLogRequest($request, $navUrl, $action);
            /** @var Queue $queueItem */
            $queueItem = $this->registry->registry('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM');

            $entityId = $queueItem ? $queueItem->getEntityId() : 'N/A';
            if ($queueItem && $queueItem->getCode() == \MalibuCommerce\MConnect\Model\Queue\Order::CODE
                && $queueItem->getAction() == \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT
            ) {
                /** @var \Magento\Sales\Model\Order $order */
                $order = $this->salesOrderFactory->create()->load($entityId);
                $entityId = '#' . $order->getIncrementId();
            }

            $this->mConnectMailer->sendErrorEmail([
                'error_title'   => 'An error occurred when processing Navision API call',
                'queue_item_id' => $queueItem ? $queueItem->getId() : 'N/A',
                'action'        => $queueItem ? $queueItem->getAction() : 'N/A',
                'entity_type'   => $queueItem ? $queueItem->getCode() : 'N/A',
                'entity_id'     => $entityId,
                'nav_url'       => $navUrl,
                'request'       => is_array($request) ? print_r($request, true) : $request,
                'error'         => $e->getMessage()
            ]);
        } catch (\Throwable $e) {

            return false;
        }

        return true;
    }

    /**
     * @param string|array $request
     * @param string       $location
     * @param string       $action
     * @param string|int   $code
     * @param string       $header
     * @param string       $body
     *
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function logRequest($request, $location, $action, $code, $header, $body)
    {
        try {
            if (!$this->mConnectConfig->get('nav_connection/log')) {
                return false;
            }

            /** @var Queue $queueItem */
            $queueItem = $this->registry->registry('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE_ITEM');
            if (!$queueItem || !$queueItem->getId()) {

                return false;
            }

            $request = $this->prepareLogRequest($request, $location, $action);
            $response = [
                'Code'          => $code,
                'Headers'       => $header,
                'Response Data' => $this->decodeRequest('/<responseXML>(.*)<\/responseXML>/', $body)
            ];
            $logData = [
                'Request'  => $request,
                'Response' => $response
            ];

            if ($this->isLogDataToDb()) {
                $this->queueResourceModel->saveLog($queueItem->getId(), $logData);
            } else {
                $this->logger
                    ->setQueueItemId($queueItem->getId())
                    ->info('Debug Data', $logData);
            }
        } catch (\Throwable $e) {
            $this->logger
                ->setQueueItemId($queueItem->getId())
                ->error($e);
        }

        return true;
    }

    /**
     * @param string|array $request
     * @param string       $location
     * @param string       $action
     *
     * @return array
     */
    public function prepareLogRequest($request, $location, $action)
    {
        return [
            'Time'         => date('r'),
            'Location'     => $location,
            'PID'          => getmypid(),
            'Action'       => $action,
            'Request Data' => $this->decodeRequest('/<ns1:requestXML>(.*)<\/ns1:requestXML>/', $request),
        ];
    }

    /**
     * @param string $pattern
     * @param string $value
     *
     * @return bool|string
     */
    public function decodeRequest($pattern, $value)
    {
        if (is_string($value) && preg_match($pattern, $value, $matches)
            && isset($matches[1]) && preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $matches[1])
        ) {
            return base64_decode($matches[1]);
        }

        return $value;
    }

    /**
     * @return boolean
     */
    public function isLogDataToDb()
    {
        return (bool)$this->mConnectConfig->get('nav_connection/log_to_db');
    }
}
