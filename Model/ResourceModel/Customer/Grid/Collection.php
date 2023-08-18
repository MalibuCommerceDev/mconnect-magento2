<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Model\ResourceModel\Customer\Grid;

use Magento\Customer\Model\ResourceModel\Grid\Collection as OriginalCollection;

class Collection extends OriginalCollection
{
    protected function _renderFiltersBefore()
    {
        $table = $this->getTable('malibucommerce_mconnect_queue');
        $subSelect2 = $this->getConnection()->select()
            ->from($table, ['entity_id', 'finished_at' => new \Zend_Db_Expr('MAX(finished_at)')])
            ->where('code = ?', \MalibuCommerce\MConnect\Model\Queue\Customer::CODE)
            ->where('action = \'export\'')
            ->group('entity_id');


        $subSelect = $this->getConnection()->select()
            ->from(
                ['mc_q2' => new \Zend_Db_Expr('(' . $subSelect2 . ')')],
                ['mc_status' => 'mc_q3.status', 'mc_message' => 'mc_q3.message', 'mc_entity_id' => 'mc_q3.entity_id', 'mc_finished_at' => 'mc_q3.finished_at']
            )
            ->join(
                ['mc_q3'=> $table],
                $this->getConnection()->quoteInto(
                    "mc_q2.entity_id = mc_q3.entity_id AND mc_q3.finished_at = mc_q2.finished_at AND mc_q3.code = ? AND mc_q3.action = 'export'",
                    \MalibuCommerce\MConnect\Model\Queue\Customer::CODE
                ),
                []
            );

        $this->getSelect()->joinLeft(
            ['mc_q1' => new \Zend_Db_Expr('(' . $subSelect . ')')],
            'mc_q1.mc_entity_id = main_table.entity_id',
            ['mc_q1.*']
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
