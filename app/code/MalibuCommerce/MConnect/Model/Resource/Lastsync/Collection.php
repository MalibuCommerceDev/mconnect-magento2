<?php
namespace MalibuCommerce\MConnect\Model\Resource\Lastsync;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Lastsync', 'MalibuCommerce\MConnect\Model\Resource\Lastsync');
    }
}
