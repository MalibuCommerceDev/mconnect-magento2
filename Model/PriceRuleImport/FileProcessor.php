<?php

namespace MalibuCommerce\MConnect\Model\PriceRuleImport;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Name;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Math\Random;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Psr\Log\LoggerInterface;

class FileProcessor
{
    const IMPORT_FILE_DIR = 'price_rule_import';

    /**
     * @var UploaderFactory
     */
    private UploaderFactory $uploaderFactory;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var Size
     */
    private Size $fileSize;

    /**
     * @var Random
     */
    private Random $random;

    /**
     * @var Name
     */
    private Name $fileNameLookup;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param Size $fileSize
     * @param Random $random
     * @param Name $fileNameLookup
     * @param LoggerInterface $logger
     */
    public function __construct(
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        Size $fileSize,
        Random $random,
        Name $fileNameLookup,
        LoggerInterface $logger
    ) {
        $this->uploaderFactory = $uploaderFactory;
        $this->filesystem = $filesystem;
        $this->fileSize = $fileSize;
        $this->random = $random;
        $this->fileNameLookup = $fileNameLookup;
        $this->logger = $logger;
    }

    /**
     * @param bool $isWrite
     * @return ReadInterface|WriteInterface
     * @throws FileSystemException
     */
    public function getTmpDirectory(bool $isWrite = false)
    {
        return $isWrite ?
            $this->filesystem->getDirectoryWrite(DirectoryList::TMP) :
            $this->filesystem->getDirectoryRead(DirectoryList::TMP);
    }

    /**
     * @param bool $isWrite
     * @return ReadInterface|WriteInterface
     * @throws FileSystemException
     */
    public function getImportDirectory(bool $isWrite = false)
    {
        return $isWrite ?
            $this->filesystem->getDirectoryWrite(DirectoryList::VAR_IMPORT_EXPORT) :
            $this->filesystem->getDirectoryRead(DirectoryList::VAR_IMPORT_EXPORT);
    }

    /**
     * @param ReadInterface|WriteInterface $directory
     * @param string $fileName
     * @return string
     */
    public function getDirectoryFilePath($directory, string $fileName): string
    {
        return $this->buildFilePath(
            $directory->getAbsolutePath(FileProcessor::IMPORT_FILE_DIR),
            $fileName
        );
    }

    /**
     * Retrieve path
     *
     * @param string $path
     * @param string $fileName
     * @return string
     */
    public function buildFilePath(string $path, string $fileName): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $fileName = ltrim($fileName, DIRECTORY_SEPARATOR);
        return $path . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * Save file to temp directory
     *
     * @param  string $fileId
     *
     * @return array
     */
    public function saveFileToTmpDir(string $fileId): array
    {
        try {
            $tmpDirectory = $this->getTmpDirectory(true);
            $tmpPath = $tmpDirectory->getAbsolutePath(self::IMPORT_FILE_DIR);
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
            $uploader->setAllowedExtensions(['csv']);
            $uploader->skipDbProcessing(true);
            $uploader->setAllowRenameFiles(true);
            $fileName = $this->random->getRandomString(32) . '.' . $uploader->getFileExtension();

            $uploader->addValidateCallback('size', $this, 'validateMaxSize');

            $result = $uploader->save($tmpPath, $fileName);
            unset($result['path']);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $result;
    }

    /**
     * Checking file for moving and move it
     *
     * @param string $fileName
     * @return string
     *
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function moveFileFromTmp(string $fileName): string
    {
        $tmpDirectory = $this->getTmpDirectory(true);
        $importDirectory = $this->getImportDirectory(true);
        $varTmpPath = $tmpDirectory->getAbsolutePath(self::IMPORT_FILE_DIR);
        $varImportPath = $importDirectory->getAbsolutePath(self::IMPORT_FILE_DIR);

        $newFileName = $this->fileNameLookup->getNewFileName(
            $this->buildFilePath($varImportPath, $fileName)
        );
        $importFilePath = $this->buildFilePath($varImportPath, $newFileName);
        $tmpImportFilePath = $this->buildFilePath($varTmpPath, $fileName);

        try {
            $importDirectory->renameFile($tmpImportFilePath, $importFilePath);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new LocalizedException(__('Something went wrong while saving the file(s).'), $e);
        }

        return $newFileName;
    }

    /**
     * Validation callback for checking max file size
     *
     * @param  string $filePath Path to temporary uploaded file
     * @return void
     * @throws LocalizedException
     */
    public function validateMaxSize(string $filePath): void
    {
        $maxFileSize = $this->fileSize->getMaxFileSize();
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::SYS_TMP);

        if ($maxFileSize > 0 && $directory->stat(
                $directory->getRelativePath($filePath)
            )['size'] > $maxFileSize * 1024
        ) {
            throw new LocalizedException(
                __('The file you\'re uploading exceeds the server size limit of %1 kilobytes.', $maxFileSize)
            );
        }
    }
}
