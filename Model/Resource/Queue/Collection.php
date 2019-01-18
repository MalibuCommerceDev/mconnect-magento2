<?php

namespace MalibuCommerce\MConnect\Model\Resource\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Queue', 'MalibuCommerce\MConnect\Model\Resource\Queue');
    }

    public function findMatchingPending($code, $action, $websiteId = 0, $navPageNumber = 0, $id = null, $details = '')
    {
        $this
            ->addFieldToFilter('status', [
                'in' => [
                    \MalibuCommerce\Mconnect\Model\Queue::STATUS_PENDING,
                    \MalibuCommerce\Mconnect\Model\Queue::STATUS_RUNNING
                ]
            ])
            ->addFieldToFilter('code', $code)
            ->addFieldToFilter('action', $action)
            ->addFieldToFilter('website_id', $websiteId)
            ->addFieldToFilter('nav_page_num', $navPageNumber);

        if ($id === null) {
            $this->addFieldToFilter('entity_id', array('null' => true));
        } else {
            $this->addFieldToFilter('entity_id', $id);
        }
        if (empty($details)) {
            $this->addFieldToFilter('details', array('null' => true));
        } else {
            $this->addFieldToFilter('details', $details);
        }

        return $this;
    }

    public function olderThanDays($value, $date)
    {
        $gmtDate = $date->gmtDate();
        $this->getSelect()->where(new \Zend_Db_Expr('main_table.created_at < "' . $gmtDate . '" - INTERVAL ' . (int)$value . ' DAY'));

        return $this;
    }

    public function olderThanMinutes($value, $date)
    {
        $gmtDate = $date->gmtDate();
        $this->getSelect()->where(new \Zend_Db_Expr('main_table.created_at < "' . $gmtDate . '" - INTERVAL ' . (int)$value . ' MINUTE'));

        return $this;
    }
}
