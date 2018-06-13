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
                'status' => \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS,
                'message' => $message
            ],
            [
                'entity_id =?' => $entityId
            ]
        );
    }
}
