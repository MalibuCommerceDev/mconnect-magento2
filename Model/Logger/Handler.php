<?php

namespace MalibuCommerce\MConnect\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use \Monolog\Logger;
use Monolog\Utils;

class Handler extends Base
{
    const FILE_NAME_PREFIX = '/var/log/mconnect';

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = self::FILE_NAME_PREFIX . DIRECTORY_SEPARATOR . 'navision_soap.log';

    /**
     * File path
     *
     * @var string|null
     */
    protected $filePath = null;

    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @param DriverInterface $filesystem
     * @param null $filePath
     * @param null $fileName
     *
     * @throws \Exception
     */
    public function __construct(DriverInterface $filesystem, $filePath = null, $fileName = null)
    {
        $this->filePath = $filePath;

        parent::__construct($filesystem, $filePath, $fileName);
    }

    /**
     * Set queue item id to change log file name
     *
     * @param int $queueItemId
     *
     * @return $this
     */
    public function setQueueItemId(int $queueItemId): Handler
    {
        $this->fileName = self::FILE_NAME_PREFIX . DIRECTORY_SEPARATOR . 'queue_' . $queueItemId . '.log';
        $this->url = Utils::canonicalizePath(
            $this->filePath ? $this->filePath . $this->fileName : BP . DIRECTORY_SEPARATOR . $this->fileName
        );

        return $this;
    }
}
