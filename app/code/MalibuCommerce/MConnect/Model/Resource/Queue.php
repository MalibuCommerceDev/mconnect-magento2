<?php
namespace MalibuCommerce\MConnect\Model\Resource;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_queue', 'id');
    }
}
