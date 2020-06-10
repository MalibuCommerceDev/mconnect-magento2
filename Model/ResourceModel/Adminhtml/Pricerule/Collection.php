<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Pricerule;

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
        $this->_init(
            \MalibuCommerce\MConnect\Model\Adminhtml\Pricerule::class,
            \MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Pricerule::class
        );
    }
}
