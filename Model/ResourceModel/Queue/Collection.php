<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\Queue;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    public function _construct()
    {
        $this->_init(\MalibuCommerce\MConnect\Model\Queue::class, \MalibuCommerce\MConnect\Model\ResourceModel\Queue::class);
    }

    /**
     * @param string        $code
     * @param string        $action
     * @param int           $websiteId
     * @param int           $navPageNumber
     * @param int|null      $id
     * @param string        $details
     *
     * @return $this
     */
    public function findMatchingPending($code, $action, $websiteId = 0, $navPageNumber = 0, $id = null, $details = '')
    {
        $this
            ->addFieldToFilter('code', $code)
            ->addFieldToFilter('action', $action)
            ->addFieldToFilter('website_id', $websiteId)
            ->addFieldToFilter('status', [
                'in' => [
                    \MalibuCommerce\Mconnect\Model\Queue::STATUS_PENDING,
                    \MalibuCommerce\Mconnect\Model\Queue::STATUS_RUNNING
                ]
            ]);
        
        if ($navPageNumber > 0) {
            $this->addFieldToFilter('nav_page_num', $navPageNumber);
        }

        if ($id === null) {
            $this->addFieldToFilter('entity_id', ['null' => true]);
        } else {
            $this->addFieldToFilter('entity_id', $id);
        }
        if (empty($details)) {
            $this->addFieldToFilter('details', ['null' => true]);
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
