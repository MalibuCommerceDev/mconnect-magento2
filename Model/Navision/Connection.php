<?php
namespace MalibuCommerce\MConnect\Model\Navision;

class Connection
{
    /**
     * @var Connection\Soap
     */
    protected $soapConnection;

    /**
     * @var  \MalibuCommerce\MConnect\Model\Navision\AbstractModel
     */
    protected $callerModel;

    public function __construct(Connection\Soap $soapConnection)
    {
        $this->soapConnection = $soapConnection;
    }

    public function __call($method, $arguments)
    {
        $this->soapConnection->setCallerModel($this->getCallerModel());

        return call_user_func_array([$this->soapConnection, $method], $arguments);
    }

    /**
     * @param AbstractModel $model
     *
     * @return $this
     */
    public function setCallerModel(AbstractModel $model)
    {
        $this->callerModel = $model;

        return $this;
    }

    /**
     * @return AbstractModel
     */
    public function getCallerModel()
    {
        return $this->callerModel;
    }
}
