<?php
namespace MalibuCommerce\MConnect\Model\Navision;


class Product extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * Product constructor
     *
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     * @param Connection $mConnectNavisionConnection
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection
    ) {
        $this->mConnectConfig = $mConnectConfig;

        parent::__construct(
            $mConnectNavisionConnection
        );
    }

    /**
     * Navision export products
     *
     * @param int $page
     * @param bool $lastUpdated
     * @return \simpleXMLElement
     */
    public function export($page = 0, $lastUpdated = false)
    {
        $config = $this->mConnectConfig;
        $max    = $config->get('product/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }
        return $this->_export('item_export', $parameters);
    }

    /**
     * Navision export product
     *
     * @param $navId
     * @return \simpleXMLElement
     */
    public function exportSingle($navId)
    {
        return $this->_export('item_export', array('item_nav_id' => $navId));
    }
}
