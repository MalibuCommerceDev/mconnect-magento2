<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Model\PriceRuleImport;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\File\Name;
use Magento\Framework\Filesystem;
use MalibuCommerce\MConnect\Model\Config;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use MalibuCommerce\MConnect\Model\PriceRuleImportFactory;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\SavePriceRuleImportEntity;
use Psr\Log\LoggerInterface;

class ImportDirectoryHandlerCron
{
    const IMPORT_FILE_DIR = 'import/mconnect/pricerules';
    const DEFAULT_WEBSITE_CONFIG_PATH = 'price_rule/default_website';

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var Name
     */
    private Name $fileNameLookup;

    /**
     * @var PriceRuleImportFactory
     */
    private PriceRuleImportFactory $priceRuleImportFactory;

    /**
     * @var SavePriceRuleImportEntity
     */
    private SavePriceRuleImportEntity $savePriceRuleImport;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Filesystem $filesystem
     * @param Name $fileNameLookup
     * @param PriceRuleImportFactory $priceRuleImportFactory
     * @param SavePriceRuleImportEntity $savePriceRuleImport
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        Filesystem $filesystem,
        Name $fileNameLookup,
        PriceRuleImportFactory $priceRuleImportFactory,
        SavePriceRuleImportEntity $savePriceRuleImport,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->fileNameLookup = $fileNameLookup;
        $this->priceRuleImportFactory = $priceRuleImportFactory;
        $this->savePriceRuleImport = $savePriceRuleImport;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return void
     * @throws FileSystemException
     * @throws \Throwable
     */
    public function execute()
    {
        $websiteId = $this->config->get(self::DEFAULT_WEBSITE_CONFIG_PATH);
        if (!$this->config->isModuleEnabled() || !$websiteId) {
            return;
        }

        try {
            $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $files = $varDir->search("*.csv", self::IMPORT_FILE_DIR);

            if (empty($files)) {
                return;
            }

            $filesWithAbsolutePath = [];
            foreach ($files as $file) {
                $filesWithAbsolutePath[] = $varDir->getAbsolutePath($file);
            }

            //Sort files by date
            usort($filesWithAbsolutePath, fn($a, $b) => -(filemtime($a) - filemtime($b)));

            //newest file will be processed
            $file = array_shift($filesWithAbsolutePath);
            $fileName = basename($file);

            $importDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_IMPORT_EXPORT);
            $varImportPath = $importDirectory->getAbsolutePath(FileProcessor::IMPORT_FILE_DIR);
            $newFileName = $this->fileNameLookup->getNewFileName(
                $this->buildFilePath($varImportPath, $fileName)
            );
            $newFile = $this->buildFilePath($varImportPath, $newFileName);

            $importDirectory->renameFile($file, $newFile);

            $importModel = $this->priceRuleImportFactory->create(['data' => [
                PriceRuleImport::WEBSITE_ID => $websiteId,
                PriceRuleImport::FILENAME => $newFileName
            ]]);
            $this->savePriceRuleImport->execute($importModel);

            foreach ($filesWithAbsolutePath as $file) {
                $varDir->delete($file);
            }
        } catch (\Throwable $e) {
            $this->logger->critical($e);
            throw $e;
        }
    }

    /**
     * Retrieve path
     *
     * @param string $path
     * @param string $fileName
     * @return string
     */
    private function buildFilePath(string $path, string $fileName): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $fileName = ltrim($fileName, DIRECTORY_SEPARATOR);
        return $path . DIRECTORY_SEPARATOR . $fileName;
    }
}
