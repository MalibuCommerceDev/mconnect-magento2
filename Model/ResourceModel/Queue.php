<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Serialize\Serializer\Json;

class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Serializer interface instance.
     *
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $serializer;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        $connectionName = null
    ) {
        $this->serializer = $serializer ? : ObjectManager::getInstance()->get(Json::class);
        parent::__construct($context, $connectionName);
    }

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
        $select = $adapter->select()
            ->from($this->getMainTable(), ['logs'])
            ->where('id = ?', (int)$itemId);

        $logs = [];
        $result = $adapter->fetchOne($select);
        if ($result) {
            try {
                $logs = $this->serializer->unserialize($result);
                // support old format
                if (!empty($logs) && (array_keys($logs) !== range(0, count($logs) - 1))) {
                    $logs = [$logs];
                }
            } catch (\Throwable $e) {
            }
        }


        if (!empty($logs)) {
            $logs[] = $logData;
        } else {
            $logs = [$logData];
        }

        try {
            $logs = $this->serializer->serialize($logs);
            if (mb_strlen($logs) < Table::MAX_TEXT_SIZE) {
                return $adapter->update(
                    $this->getMainTable(),
                    ['logs'  => $logs],
                    ['id = ?' => (int)$itemId]
                );
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * @param int $itemId
     *
     * @return array
     */
    public function getLog($itemId)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['logs'])
            ->where('id = ?', (int)$itemId);

        $result = $adapter->fetchOne($select);
        if ($result) {
            try {
                $logs = $this->serializer->unserialize($result);
                // @todo change it to array_is_list() - added in PHP 8.1
                if (array_keys($logs) === range(0, count($logs) - 1)) {
                    return $logs;
                }

                // support old format
                return [$logs];
            } catch (\Throwable $e) {

                return [[$e->getMessage() => []]];
            }
        }

        return [['No recorded logs' => []]];
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
            ['retry_count' => new \Zend_Db_Expr('retry_count+1')],
            ['id = ?' => (int)$itemId]
        );
    }
}
