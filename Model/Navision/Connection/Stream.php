<?php

namespace MalibuCommerce\MConnect\Model\Navision\Connection;

use Magento\Framework\App\Filesystem\DirectoryList;

class Stream
{
    const NAV_WSDL_FILE = 'nav.wsdl';

    /**
     * @var string
     */
    protected $streamUri;

    /**
     * @var string
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

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Framework\Filesystem $filesystem,
        DirectoryList $directoryList
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
    }

    public function stream_open($streamUri)
    {
        $this->streamUri = $streamUri;
        $this->initStream();
        if (empty($this->streamData)) {
            throw new \RuntimeException('SOAP-ERROR: Couldn\'t load WSDL');
        }

        $tmpDir = $this->filesystem->getDirectoryWrite(DirectoryList::TMP);
        $tmpDir->writeFile(self::NAV_WSDL_FILE, $this->streamData);

        return $tmpDir->getAbsolutePath(self::NAV_WSDL_FILE);
    }

    public function stream_close()
    {
        curl_close($this->streamCurlHandle);
    }

    public function stream_read($count)
    {
        if ($this->streamData === null || strlen($this->streamData) === 0) {
            return false;
        }
        $data = substr($this->streamData, $this->streamDataPointer, $count);
        $this->streamDataPointer += strlen($data);

        return $data;
    }

    public function stream_write($data)
    {
        if ($this->streamData === null || strlen($this->streamData) === 0) {
            return false;
        }

        return true;
    }

    public function stream_eof()
    {
        return $this->streamDataPointer > strlen($this->streamData);
    }

    public function stream_tell()
    {
        return $this->streamDataPointer;
    }

    public function stream_flush()
    {
        $this->streamData = null;
        $this->streamDataPointer = null;
    }

    public function stream_stat()
    {
        $this->initStream();

        return array(
            'size' => strlen($this->streamData),
        );
    }

    public function url_stat($streamUri, $flags)
    {
        return $this->stream_stat();
    }

    protected function initStream()
    {
        if ($this->streamData !== null) {
            return;
        }
        $config = $this->mConnectConfig;
        $this->streamCurlHandle = curl_init($this->streamUri);
        curl_setopt($this->streamCurlHandle, CURLOPT_RETURNTRANSFER, true);

        if ($config->getIsInsecureConnectionAllowed()) {
            curl_setopt($this->streamCurlHandle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($this->streamCurlHandle, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($this->streamCurlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($config->getUseNtlmAuthentication()) {
            curl_setopt($this->streamCurlHandle, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        }
        curl_setopt(
            $this->streamCurlHandle,
            CURLOPT_USERPWD,
            $config->getNavConnectionUsername() . ':' . $config->getNavConnectionPassword()
        );
        $this->streamData = trim(curl_exec($this->streamCurlHandle));

        $httpCode = curl_getinfo($this->streamCurlHandle, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            throw new \RuntimeException('SOAP-ERROR: Couldn\'t not connect to the server');
        }
        $this->streamDataPointer = 0;
    }
}
