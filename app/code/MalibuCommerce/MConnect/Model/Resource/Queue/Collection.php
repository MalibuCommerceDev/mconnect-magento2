<?php
namespace MalibuCommerce\MConnect\Model\Resource\Queue;


class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Queue', 'MalibuCommerce\MConnect\Model\Resource\Queue');
    }


    public function findMatchingPending($code, $action, $id = null, $details = array())
    {
        $this
            ->addFieldToFilter('status', \MalibuCommerce\Mconnect\Model\Queue::STATUS_PENDING)
            ->addFieldToFilter('code', $code)
            ->addFieldToFilter('action', $action)
        ;
        if ($id === null) {
            $this->addFieldToFilter('entity_id', array('null' => true));
        } else {
            $this->addFieldToFilter('entity_id', $id);
        }
        if (!count($details)) {
            $this->addFieldToFilter('details', array('null' => true));
        } else {
            $this->addFieldToFilter('details', json_encode($details));
        }
        return $this;
    }

    public function olderThanDays($value)
    {
        $this->getSelect()->where(new \Zend_Db_Expr('main_table.created_at < "' . date('Y-m-d H:i:s') . '" - INTERVAL ' . $value . ' DAY'));
        return $this;
    }

    public function olderThanMinutes($value)
    {
        $this->getSelect()->where(new \Zend_Db_Expr('main_table.created_at < "' . date('Y-m-d H:i:s') . '" - INTERVAL ' . $value . ' MINUTE'));
        return $this;
    }
}
