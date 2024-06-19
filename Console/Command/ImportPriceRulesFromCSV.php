<?php

namespace MalibuCommerce\MConnect\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use MalibuCommerce\MConnect\Model\Queue\Pricerule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Config\Console\Command\EmulatedAdminhtmlAreaProcessor;
use Magento\Framework\Console\Cli;
use Magento\Framework\DB\Adapter\AdapterInterface;

class ImportPriceRulesFromCSV extends Command
{
    const DEFAULT_BATCH_SIZE = 1000;

    const ARGUMENT_WEBSITE_ID              = 'website_id';
    const ARGUMENT_INPUT_FILENAME          = 'file';
    const OPTION_SET_LAST_SYNC_FLAG_TO_NOW = 'lsn';

    /**
     * @var Pricerule
     */
    protected $queue;

    /**
     * Emulator adminhtml area for CLI command.
     *
     * @var EmulatedAdminhtmlAreaProcessor
     */
    protected $emulatedAreaProcessor;

    /**
     * @var FileDriver
     */
    protected FileDriver $fileDriver;

    /**
     * @var  \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected AdapterInterface $connection;

    /**
     * @var OutputInterface
     */
    protected OutputInterface $output;

    /**
     * @var WriteInterface
     */
    protected WriteInterface $directory;

    /**
     * @var Filesystem
     */
    protected Filesystem $fileStream;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resource;

    public function __construct(
        Pricerule $queue,
        EmulatedAdminhtmlAreaProcessor $emulatedAreaProcessor,
        Filesystem $filesystem,
        ResourceConnection $resource,
        FileDriver $fileDriver
    ) {
        $this->queue = $queue;
        $this->emulatedAreaProcessor = $emulatedAreaProcessor;
        $this->resource = $resource;
        $this->fileDriver = $fileDriver;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->connection = $this->resource->getConnection();

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('mconnect:import:price-rules')
            ->setDescription('Import Price Rules from CSV file exported from NAV')
            ->setDefinition([
                new InputArgument(
                    self::ARGUMENT_WEBSITE_ID,
                    InputArgument::REQUIRED,
                    'Website ID'
                ),
                new InputArgument(
                    self::ARGUMENT_INPUT_FILENAME,
                    InputArgument::REQUIRED,
                    'CSV File path relative to Magento var/ folder'
                ),
                new InputOption(
                    self::OPTION_SET_LAST_SYNC_FLAG_TO_NOW,
                    '-s',
                    InputOption::VALUE_NONE,
                    'Update Last Sync Price Rules flag to now'
                ),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $t = microtime(true);
        $this->output = $output;
        try {
            $this->emulatedAreaProcessor->process(function () use ($input, $output) {
                $websiteId = (int)$input->getArgument(self::ARGUMENT_WEBSITE_ID);
                $file = $input->getArgument(self::ARGUMENT_INPUT_FILENAME);
                $sync = $input->getOption(self::OPTION_SET_LAST_SYNC_FLAG_TO_NOW);

                if (!$file) {
                    throw new \Exception('Please add filename with path relative to magento var/ directory.');
                }
                if (!$this->directory->isExist($file)) {
                    throw new \Exception('Specified CSV file not exists.');
                } else {
                    $file = $this->directory->getAbsolutePath($file);
                }

                $this->output->writeln('<info>Process started</info>');

                $handle = fopen($file, 'r');
                $lineNumber = 0;
                $batchOfPriceRules = [];
                $updateColumns = [
                    'sku',
                    'qty_min',
                    'website_id',
                    'currency_code',
                    'navision_customer_id',
                    'customer_price_group'
                ];
                $priceRulesTable = $this->connection->getTableName('malibucommerce_mconnect_price_rule');

                while (($csvRow = fgets($handle)) !== false) {
                    // skip column names row
                    if ($lineNumber == 0) {
                        $lineNumber++;
                        continue;
                    }
                    $rowData = str_getcsv($csvRow);

                    $startTime = $endTime = null;
                    if (!empty($rowData[9])) {
                        $startTime = $rowData[9];
                        $startTime = strtotime($startTime);
                        $startTime = $startTime < 0 ? null : $startTime;
                    }

                    if (!empty($rowData[4])) {
                        $endTime = $rowData[4];
                        $endTime = strtotime($endTime);
                        $endTime = $endTime < 0 ? null : $endTime;
                    }

                    $batchOfPriceRules[] = [
                        'nav_id'               => $rowData[0],
                        'website_id'           => $websiteId,
                        'sku'                  => $rowData[12],
                        'currency_code'        => $rowData[10] ?? null,
                        'navision_customer_id' => null,
                        'qty_min'              => $rowData[5],
                        'price'                => $rowData[8],
                        'customer_price_group' => $rowData[11],
                        'date_start'           => $startTime !== null ? date('Y:m:d H:i:s', $startTime) : null,
                        'date_end'             => $endTime !== null ? date('Y:m:d H:i:s', $endTime) : null,
                    ];

                    if (count($batchOfPriceRules) >= self::DEFAULT_BATCH_SIZE) {
                        $this->importPriceRulesBatch($priceRulesTable, $batchOfPriceRules, $updateColumns);
                        $batchOfPriceRules = []; // reset data array
                    }
                    $lineNumber++;
                }
                // process remaining records in DB table
                if (count($batchOfPriceRules)) {
                    $this->importPriceRulesBatch($priceRulesTable, $batchOfPriceRules, $updateColumns);
                }
                $output->writeln(sprintf('Total processed rows %s ', $lineNumber - 1));

                if ($sync) {
                    $this->queue->setLastSyncTime($this->queue->getImportLastSyncFlagName($websiteId),
                        date('Y-m-d\TH:i:s'));
                    $output->writeln(sprintf('<info>Last sync date flag was set to now for Website ID %s!</info>',
                        $websiteId));
                }
            });

            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf('<error>%s</error>', $e->getMessage())
            );

            return Cli::RETURN_FAILURE;
        } finally {
            $t = microtime(true) - $t;
            $this->output->writeln(sprintf('<info>Processing Completed in %s seconds<info>', $t));
        }
    }

    /**
     * Update/Insert data in Table
     *
     * @param $data
     * @param $table
     * @param $columns
     *
     * @return void
     */
    protected function importPriceRulesBatch($table, $data, $updateColumns)
    {
        $affectedRows = $this->connection->insertOnDuplicate(
            $table,
            $data,
            $updateColumns
        );

        if ($affectedRows) {
            $this->output->writeln(sprintf('Total %s row affected', $affectedRows));
        } else {
            $this->output->writeln('No new add/update');
        }
    }
}
