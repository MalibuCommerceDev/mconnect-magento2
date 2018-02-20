<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Inventory extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection
    ) {
        $this->mConnectConfig = $mConnectConfig;

        parent::__construct(
            $mConnectNavisionConnection
        );
    }

    public function export($page = 0, $lastUpdated = false)
    {
        $config = $this->mConnectConfig;
        $max    = $config->get('inventory/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }
        return $this->_export('inventory_export', $parameters);
    }

}