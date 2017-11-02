<?php

namespace MalibuCommerce\MConnect\Model\Resource\Adminhtml\Queue;

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
        $this->_init('MalibuCommerce\MConnect\Model\Adminhtml\Queue',
            'MalibuCommerce\MConnect\Model\Resource\Adminhtml\Queue');
    }

    /**
     * @param string $valueField
     * @param string $labelField
     * @param array  $additional
     *
     * @return array
     */
    protected function _toOptionArray($valueField = 'id', $labelField = 'code', $additional = [])
    {
        return parent::_toOptionArray($valueField, $labelField, $additional);
    }
}