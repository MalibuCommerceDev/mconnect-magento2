<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PriceRuleImport extends AbstractDb
{
    public const TABLE_NAME = 'malibucommerce_mconnect_price_rule_import';
    public const TABLE_PRIMARY_KEY = 'uuid';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::TABLE_PRIMARY_KEY);
    }

    /**
     * @param string $uuid
     * @return string|null
     */
    public function getFilenameById(string $uuid): ?string
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(self::TABLE_NAME, ['filename'])
            ->where('uuid = ?', $uuid);

        $filename = $connection->fetchOne($select);
        return false === $filename ? null : $filename;
    }
}
