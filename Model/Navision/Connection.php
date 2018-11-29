<?php
namespace MalibuCommerce\MConnect\Model\Navision;

class Connection
{
    /**
     * @var Connection\SoapFactory
     */
    protected $soapConnectionFactory;

    public function __construct(
        Connection\SoapFactory $soapConnectionFactory
    ) {
        $this->soapConnectionFactory = $soapConnectionFactory;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->soapConnectionFactory->create(), $method), $arguments);
    }
}
