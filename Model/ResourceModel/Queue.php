<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_queue', 'id');
    }

    /**
     * @param int $entityId
     * @param string $code
     * @param null|string $message
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
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

    /**
     * @param int $entityId
     * @param string $code
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
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

    /**
     * @param int $itemId
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteQueueItemById($itemId)
    {
        if (!is_array($itemId)) {
            $itemId = [$itemId];
        }

        $adapter = $this->getConnection();

        return $adapter->delete($this->getMainTable(), ['id IN (?)' => $itemId]);
    }

    /**
     * @param int $itemId
     * @param string $logData
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function saveLog($itemId, $logData)
    {
        $adapter = $this->getConnection();

        return $adapter->update(
            $this->getMainTable(),
            [
                'logs'  => $logData,
            ],
            [
                'id = ?' => (int)$itemId,
            ]
        );
    }

    /**
     * @param int $itemId
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLog($itemId)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['logs'])
            ->where('id = ?', (int)$itemId);

        return $adapter->fetchOne($select);
    }

    /**
     * @param int $itemId
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function incrementRetryCount($itemId)
    {
        $adapter = $this->getConnection();

        return $adapter->update(
            $this->getMainTable(),
            [
                'retry_count' => new \Zend_Db_Expr('retry_count+1'),
            ],
            [
                'id = ?' => (int)$itemId,
            ]
        );
    }
}
