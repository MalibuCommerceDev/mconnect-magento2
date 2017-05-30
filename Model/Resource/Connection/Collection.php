<?php
namespace MalibuCommerce\MConnect\Model\Resource\Connection;


class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Connection', 'MalibuCommerce\MConnect\Model\Resource\Connection');
    }
}
