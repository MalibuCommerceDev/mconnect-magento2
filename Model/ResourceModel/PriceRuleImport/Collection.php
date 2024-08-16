<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport;

use MalibuCommerce\MConnect\Model\PriceRuleImport as Model;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport as Resource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define collection item type and corresponding table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Model::class, Resource::class);
        $this->setMainTable(Resource::TABLE_NAME);
    }
}
