<?php

namespace MalibuCommerce\MConnect\Model\PriceRuleImport;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\WebsiteRepositoryInterface;
use MalibuCommerce\MConnect\Model\PriceRuleImport;

class Validator
{
    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @var WebsiteRepositoryInterface
     */
    private WebsiteRepositoryInterface $websiteRepository;

    /**
     * @param FileProcessor $fileProcessor
     * @param WebsiteRepositoryInterface $websiteRepository
     */
    public function __construct(
        FileProcessor $fileProcessor,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->fileProcessor = $fileProcessor;
        $this->websiteRepository = $websiteRepository;
    }


    /**
     * @param PriceRuleImport $priceRuleImport
     * @return void
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function validate(PriceRuleImport $priceRuleImport): void
    {
        //Validate website ID
        try {
            $this->websiteRepository->getById($priceRuleImport->getWebsiteId());
        } catch (\Exception $e) {
            throw new LocalizedException(__('Invalid website ID.'));
        }

        //validate CSV file columns
        $this->validateCsvFile($priceRuleImport);
    }

    /**
     * @param PriceRuleImport $priceRuleImport
     * @return void
     * @throws LocalizedException
     * @throws FileSystemException
     */
    private function validateCsvFile(PriceRuleImport $priceRuleImport): void
    {
        //if the import is not saved yet, the file is in the tmp directory
        $directory = empty($priceRuleImport->getCreatedAt())
            ? $this->fileProcessor->getTmpDirectory()
            : $this->fileProcessor->getImportDirectory();

        $filePath = $this->fileProcessor->getDirectoryFilePath($directory, $priceRuleImport->getFilename());

        //read the first line of the file to validate the header
        $header = $directory->openFile($filePath)->readCsv();

        if (empty($header)) {
            throw new LocalizedException(__('Invalid CSV file. Please check the file content.'));
        }

        if ($headerDiff = array_diff(PriceRuleImport::CSV_HEADERS, $header)) {
            throw new LocalizedException(__(
                'Invalid CSV file. Column names are required: %1',
                implode(', ', $headerDiff))
            );
        }
    }
}
