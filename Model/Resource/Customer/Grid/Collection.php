<?php

namespace MalibuCommerce\MConnect\Model\Resource\Customer\Grid;

use Magento\Customer\Model\ResourceModel\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    protected function _renderFiltersBefore()
    {
        $table = $this->getTable('malibucommerce_mconnect_queue');
        $subSelect = $this->getConnection()->select()
            ->from(['mc_q1' => $table], ['mc_status' => 'status', 'mc_message' => 'message', 'mc_entity_id' => 'entity_id'])
            ->group('mc_q1.entity_id')
            ->order('mc_q1.finished_at ' .  \Magento\Framework\DB\Select::SQL_DESC);

        $this->getSelect()->joinLeft(
            ['mc_queue' => new \Zend_Db_Expr('(' . $subSelect . ')')],
            'main_table.entity_id = mc_queue.mc_entity_id',
            ['mc_queue.*']
        );

        parent::_renderFiltersBefore();
    }
}