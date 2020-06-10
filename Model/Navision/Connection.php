<?php
namespace MalibuCommerce\MConnect\Model\Navision;

class Connection
{
    /**
     * @var Connection\Soap
     */
    protected $soapConnection;

    public function __construct(Connection\Soap $soapConnection)
    {
        $this->soapConnection = $soapConnection;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array([$this->soapConnection, $method], $arguments);
    }
}
