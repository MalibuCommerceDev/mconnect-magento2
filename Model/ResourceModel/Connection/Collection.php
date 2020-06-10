<?php
namespace MalibuCommerce\MConnect\Model\ResourceModel\Connection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init(\MalibuCommerce\MConnect\Model\Connection::class, \MalibuCommerce\MConnect\Model\ResourceModel\Connection::class);
    }
}
