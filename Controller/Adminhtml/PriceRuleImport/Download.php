<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport;
use MalibuCommerce\MConnect\Model\PriceRuleImport\FileProcessor;

class Download extends AbstractController
{
    /**
     * @var PriceRuleImport
     */
    private PriceRuleImport $priceRuleImportResource;

    /**
     * @var FileFactory
     */
    private FileFactory $fileFactory;

    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @param Context $context
     * @param PriceRuleImport $priceRuleImportResource
     * @param FileFactory $fileFactory
     * @param FileProcessor $fileProcessor
     */
    public function __construct(
        Context $context,
        PriceRuleImport $priceRuleImportResource,
        FileFactory $fileFactory,
        FileProcessor $fileProcessor
    ) {
        parent::__construct($context);
        $this->priceRuleImportResource = $priceRuleImportResource;
        $this->fileFactory = $fileFactory;
        $this->fileProcessor = $fileProcessor;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $uuid = $this->getRequest()->getParam('uuid');
            $filename = $this->priceRuleImportResource->getFilenameById($uuid);

            $directory = $this->fileProcessor->getImportDirectory();
            $filePath = $this->fileProcessor->getDirectoryFilePath($directory, $filename);

            return $this->fileFactory->create(
                $filename,
                ['type' => 'filename', 'value' => $filePath],
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while downloading the file.'));
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('*/*/index');
            return $resultRedirect;
        }
    }
}
