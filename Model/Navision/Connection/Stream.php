<?php
namespace MalibuCommerce\MConnect\Model\Navision\Connection;


class Stream
{
    protected $_path;
    protected $_stream;
    protected $_pointer;
    protected $_ch;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    protected $directoryList;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        $this->directoryList = $directoryList;
        $this->mConnectConfig = $mConnectConfig;
        $this->stream_open($this->mConnectConfig->getNavConnectionUrl(), null, null, $mConnectConfig);
    }
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->_path = $path;
        $this->_initialize();

        $filename    = 'nav.wsdl';
        $destination = $this->directoryList->getPath('tmp') . "{$filename}";

        file_put_contents($destination, $this->_stream);

        return $destination;
    }

    public function stream_close()
    {
        curl_close($this->_ch);
    }

    public function stream_read($count)
    {
        if ($this->_stream === null || strlen($this->_stream) === 0) {
            return false;
        }
        $data = substr($this->_stream, $this->_pointer, $count);
        $this->_pointer += strlen($data);
        return $data;
    }

    public function stream_write($data)
    {
        if ($this->_stream === null || strlen($this->_stream) === 0) {
            return false;
        }
        return true;
    }

    public function stream_eof()
    {
        return $this->_pointer > strlen($this->_stream);
    }

    public function stream_tell()
    {
        return $this->_pointer;
    }

    public function stream_flush()
    {
        $this->_stream = null;
        $this->_pointer = null;
    }

    public function stream_stat()
    {
        $this->_initialize();
        return array(
            'size' => strlen($this->_stream),
        );
    }

    public function url_stat($path, $flags)
    {
        return $this->stream_stat();
    }

    protected function _initialize()
    {
        if ($this->_stream !== null) {
            return;
        }
        $config = $this->mConnectConfig;
        $this->_ch = curl_init($this->_path);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->_ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($this->_ch, CURLOPT_USERPWD, $config->getNavConnectionUsername() . ':' . $config->getNavConnectionPassword());
        $this->_stream = trim(curl_exec($this->_ch));
        $this->_pointer = 0;
    }
}
