<?php

namespace MalibuCommerce\MConnect\Model\Resource\Adminhtml\Pricerule;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'mconnect_pricerule_collection';
    protected $_eventObject = 'pricerule_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Adminhtml\Pricerule',
            'MalibuCommerce\MConnect\Model\Resource\Adminhtml\Pricerule');
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