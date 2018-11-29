<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection;

use Magento\Framework\App\Filesystem\DirectoryList;

class Stream
{
    const NAV_WSDL_FILE_MASK = 'nav_%s.wsdl';

    /**
     * @var string
     */
    protected $streamUri;

    /**
     * @var []
     */
    protected $streamData;

    /**
     * @var int
     */
    protected $streamDataPointer;

    /**
     * @var resource
     */
    protected $streamCurlHandle;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var int
     */
    protected $websiteId = 0;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Framework\Filesystem $filesystem,
        DirectoryList $directoryList
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
    }

    public function setWebsiteId($websiteId)
    {
        $this->websiteId = $websiteId;
    }

    public function getWebsiteId()
    {
        return $this->websiteId;
    }

    public function stream_open($streamUri)
    {
        $this->streamUri = $streamUri;
        $this->initStream();

        $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $wsdlFileName = sprintf(self::NAV_WSDL_FILE_MASK, $this->getWebsiteId());

        $tmpDir->writeFile($wsdlFileName, $this->getStreamData());

        return $tmpDir->getAbsolutePath($wsdlFileName);
    }

    protected function getStreamData()
    {
        if (!array_key_exists($this->getWebsiteId(), $this->streamData)) {

            return null;
        }

        return $this->streamData[$this->getWebsiteId()];
    }

    protected function setStreamData($data)
    {
        $this->streamData[$this->getWebsiteId()] = $data;
    }

    public function stream_close()
    {
        curl_close($this->streamCurlHandle);
    }

    public function stream_read($count)
    {
        if ($this->getStreamData() === null || strlen($this->getStreamData()) === 0) {
            return false;
        }
        $data = substr($this->getStreamData(), $this->streamDataPointer, $count);
        $this->streamDataPointer += strlen($data);

        return $data;
    }

    public function stream_write($data)
    {
        if ($this->getStreamData() === null || strlen($this->getStreamData()) === 0) {
            return false;
        }

        return true;
    }

    public function stream_eof()
    {
        return $this->streamDataPointer > strlen($this->getStreamData());
    }

    public function stream_tell()
    {
        return $this->streamDataPointer;
    }

    public function stream_flush()
    {
        $this->setStreamData(null);
        $this->streamDataPointer = null;
    }

    public function stream_stat()
    {
        $this->initStream();

        return array(
            'size' => strlen($this->getStreamData()),
        );
    }

    public function url_stat($streamUri, $flags)
    {
        return $this->stream_stat();
    }

    protected function initStream()
    {
        if ($this->getStreamData() !== null) {
            return;
        }
        $streamUri = $this->streamUri;
        $config = $this->mConnectConfig;
        $this->streamCurlHandle = curl_init($streamUri);
        curl_setopt($this->streamCurlHandle, CURLOPT_RETURNTRANSFER, true);

        if ($config->getIsInsecureConnectionAllowed($this->getWebsiteId())) {
            curl_setopt($this->streamCurlHandle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($this->streamCurlHandle, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($this->streamCurlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($config->getUseNtlmAuthentication($this->getWebsiteId())) {
            curl_setopt($this->streamCurlHandle, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        }
        curl_setopt(
            $this->streamCurlHandle,
            CURLOPT_USERPWD,
            $config->getNavConnectionUsername($this->getWebsiteId()) . ':' . $config->getNavConnectionPassword($this->getWebsiteId())
        );
        $this->setStreamData(trim(curl_exec($this->streamCurlHandle)));

        $httpCode = curl_getinfo($this->streamCurlHandle, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            throw new \RuntimeException('SOAP-ERROR: Couldn\'t not connect to the server, URL: ' . $streamUri);
        }

        if (empty($this->getStreamData())) {
            throw new \RuntimeException('SOAP-ERROR: Couldn\'t load WSDL from URL: ' . $streamUri);
        }

        $this->streamDataPointer = 0;
    }
}
