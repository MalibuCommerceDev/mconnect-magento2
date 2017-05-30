<?php
namespace MalibuCommerce\MConnect\Model\Navision;


class Connection
{
    protected $_connection;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Connection\Soap
     */
    protected $mConnectNavisionConnectionSoap;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Connection\Soap $mConnectNavisionConnectionSoap
    )
    {
        $this->mConnectNavisionConnectionSoap = $mConnectNavisionConnectionSoap;
        $this->_connection = $this->mConnectNavisionConnectionSoap;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->_connection, $method), $arguments);
    }
}
