<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Model\ResourceModel\Customer\Grid;

use Magento\Customer\Model\ResourceModel\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    protected function _renderFiltersBefore()
    {
        $table = $this->getTable('malibucommerce_mconnect_queue');
        $subSelect = $this->getConnection()->select()
            ->from(['mc_q1' => $table], ['mc_status' => 'status', 'mc_message' => 'message', 'mc_entity_id' => 'entity_id', 'mc_code' => 'code'])
            ->order('mc_q1.finished_at ' .  \Magento\Framework\DB\Select::SQL_DESC)
            ->limit(1);

        $this->getSelect()->joinLeft(
            ['mc_queue' => new \Zend_Db_Expr('(' . $subSelect . ')')],
            $this->getConnection()->quoteInto(
                'main_table.entity_id = mc_queue.mc_entity_id AND mc_queue.mc_code = ?',
                \MalibuCommerce\MConnect\Model\Queue\Customer::CODE
            ),
            ['mc_queue.*']
        );

        parent::_renderFiltersBefore();
    }

    /**
     * Add field filter to collection
     *
     * @param string|array $field
     * @param null|string|array $condition
     *
     * @return Collection
     */
    public function addFieldToFilter($field, $condition = null): Collection
    {
        if ($field == 'mc_status') {
            $field = 'mc_queue.mc_status';
        }

        return parent::addFieldToFilter($field, $condition);
    }
}
