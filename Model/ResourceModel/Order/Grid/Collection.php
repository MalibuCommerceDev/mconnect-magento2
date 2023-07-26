<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\Order\Grid;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    protected function _renderFiltersBefore()
    {
        $table = $this->getTable('malibucommerce_mconnect_queue');
        $subSelect = $this->getConnection()->select()
            ->from(['mc_q1' => $table], ['mc_status' => 'status', 'mc_message' => 'message', 'mc_entity_id' => 'entity_id', 'mc_code' => 'code'])
            ->group('mc_q1.entity_id')
            ->order('mc_q1.finished_at ' .  \Magento\Framework\DB\Select::SQL_DESC);

        $this->getSelect()->joinLeft(
            ['mc_queue' => new \Zend_Db_Expr('(' . $subSelect . ')')],
            $this->getConnection()->quoteInto(
                'main_table.entity_id = mc_queue.mc_entity_id AND mc_queue.mc_code = ?',
                \MalibuCommerce\MConnect\Model\Queue\Order::CODE
            ),
            ['mc_queue.*']
        );

        parent::_renderFiltersBefore();
    }
}
