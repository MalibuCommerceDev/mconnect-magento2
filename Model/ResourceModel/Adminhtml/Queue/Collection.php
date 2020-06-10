<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'mconnect_queue_collection';
    protected $_eventObject = 'queue_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \MalibuCommerce\MConnect\Model\Adminhtml\Queue::class,
            \MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Queue::class
        );
    }
}
