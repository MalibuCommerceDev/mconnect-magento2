<?php
namespace MalibuCommerce\MConnect\Model\Resource;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_queue', 'id');
    }

    public function removePendingItemsByEntityId($entityId, $message = null)
    {
        $adapter = $this->getConnection();
        return $adapter->update(
            $this->getMainTable(),
            [
                'status' => \MalibuCommerce\MConnect\Model\Queue::STATUS_CANCELED,
                'message' => $message
            ],
            [
                'entity_id = ?' => $entityId,
                'status = ?' => \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING
            ]
        );
    }

    public function wasTheItemEverSuccessfullyExported($entityId)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['id'])
            ->where('entity_id = ?', $entityId)
            ->where('status = ?', \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS)
            ->where('action = ?', 'export')
            ->limit(1);

        return (bool) $adapter->fetchOne($select);
    }
}
