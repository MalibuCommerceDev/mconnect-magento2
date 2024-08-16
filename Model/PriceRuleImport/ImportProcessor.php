<?php

namespace MalibuCommerce\MConnect\Model\PriceRuleImport;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\SavePriceRuleImportEntity;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use Psr\Log\LoggerInterface;

class ImportProcessor
{
    const DEFAULT_BATCH_SIZE = 1000;
    const TABLE_NAME = 'malibucommerce_mconnect_price_rule';

    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @var Validator
     */
    private Validator $validator;

    /**
     * @var SavePriceRuleImportEntity
     */
    private SavePriceRuleImportEntity $savePriceRuleImportEntity;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param FileProcessor $fileProcessor
     * @param Validator $validator
     * @param SavePriceRuleImportEntity $savePriceRuleImportEntity
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        FileProcessor $fileProcessor,
        Validator $validator,
        SavePriceRuleImportEntity $savePriceRuleImportEntity,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->fileProcessor = $fileProcessor;
        $this->validator = $validator;
        $this->savePriceRuleImportEntity = $savePriceRuleImportEntity;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * @param PriceRuleImport $priceRuleImport
     * @return int
     */
    public function process(PriceRuleImport $priceRuleImport): int
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $priceRuleImport->setExecutedAt(date('Y-m-d H:i:s'));
        $priceRuleImport->setAttempts($priceRuleImport->getAttempts() + 1);

        try {
            $connection->beginTransaction();

            // Validate price rule import
            $this->validator->validate($priceRuleImport);

            //Clear existing rules
            $connection->delete($tableName);

            $directory = $this->fileProcessor->getImportDirectory();
            $filePath = $this->fileProcessor->getDirectoryFilePath($directory, $priceRuleImport->getFilename());

            $file = $directory->openFile($filePath);
            $headers = $file->readCsv();

            $updatedCount = 0;
            $batchOfPriceRules = [];
            while ($csvRow = $file->readCsv()) {
                $rowData = array_combine($headers, $csvRow);

                $startTime = strtotime($rowData['Starting Date']);
                $batchOfPriceRules[] = [
                    'nav_id'               => $rowData['Unique ID'],
                    'website_id'           => $priceRuleImport->getWebsiteId(),
                    'sku'                  => $rowData['Item No_'],
                    'currency_code'        => $rowData['Currency Code'] ?? null,
                    'qty_min'              => $rowData['Minimum Quantity'],
                    'price'                => $rowData['Unit Price'],
                    'customer_price_group' => $rowData['Sales Code'],
                    'date_start'           => $startTime && $startTime > 0 ? date('Y:m:d H:i:s', $startTime) : null,
                ];

                if (count($batchOfPriceRules) >= self::DEFAULT_BATCH_SIZE) {
                    $updatedCount += $this->importPriceRulesBatch($connection, $batchOfPriceRules);
                    $batchOfPriceRules = []; // reset data array
                }
            }

            // process remaining records in DB table
            if (!empty($batchOfPriceRules)) {
                $updatedCount += $this->importPriceRulesBatch($connection, $batchOfPriceRules);
            }
            $connection->commit();

            $priceRuleImport->setProcessedCount($updatedCount);
            $priceRuleImport->setStatus(PriceRuleImport::STATUS_COMPLETE);
        } catch (\Throwable $e) {
            $connection->rollBack();
            $priceRuleImport->setStatus(PriceRuleImport::STATUS_FAILED);
            $priceRuleImport->setMessage($e->getMessage());
            $this->logger->error($e);
        }

        $this->savePriceRuleImportEntity->execute($priceRuleImport);

        return $priceRuleImport->getProcessedCount();
    }

    /**
     * Update/Insert data in Table
     *
     * @param AdapterInterface $connection
     * @param array $data
     * @return int
     */
    protected function importPriceRulesBatch(AdapterInterface $connection, array $data): int
    {
        return $connection->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::TABLE_NAME),
            $data,
            [
                'nav_id',
                'website_id',
                'sku',
                'currency_code',
                'qty_min',
                'price',
                'customer_price_group',
                'date_start'
            ]
        );
    }
}
