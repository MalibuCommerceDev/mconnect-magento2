<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport;

use Magento\Framework\App\ResourceConnection;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport as Resource;

class SavePriceRuleImportEntity
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     *
     * @param PriceRuleImport $priceRuleImport
     * @return PriceRuleImport
     */
    public function execute(PriceRuleImport $priceRuleImport): PriceRuleImport
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(Resource::TABLE_NAME);
        try {
            $connection->beginTransaction();
            $data = $priceRuleImport->getData();
            //support for saving models retrieved from collection
            unset($data['orig_data']);

            $connection->insertOnDuplicate(
                $tableName,
                $data,
                [
                    PriceRuleImport::STATUS,
                    PriceRuleImport::PROCESSED_COUNT,
                    PriceRuleImport::ATTEMPTS,
                    PriceRuleImport::MESSAGE,
                    PriceRuleImport::EXECUTED_AT,
                ]
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $priceRuleImport;
    }
}
