<?php

namespace MalibuCommerce\MConnect\Model\Resource;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_queue', 'id');
    }

    public function removePendingItems($entityId, $code, $message = null)
    {
        $adapter = $this->getConnection();

        return $adapter->update(
            $this->getMainTable(),
            [
                'status'  => \MalibuCommerce\MConnect\Model\Queue::STATUS_CANCELED,
                'message' => $message
            ],
            [
                'entity_id = ?' => $entityId,
                'code = ?'      => $code,
                'status = ?'    => \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING
            ]
        );
    }

    public function wasTheItemEverSuccessfullyExported($entityId, $code)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['id'])
            ->where('entity_id = ?', $entityId)
            ->where('code = ?', $code)
            ->where('status = ?', \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS)
            ->where('action = ?', \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT)
            ->limit(1);

        return (bool)$adapter->fetchOne($select);
    }

    public function deleteQueueItemById($itemId)
    {
        if (!is_array($itemId)) {
            $itemId = [$itemId];
        }

        $adapter = $this->getConnection();

        return $adapter->delete($this->getMainTable(), ['id IN (?)' => $itemId]);
    }
}
