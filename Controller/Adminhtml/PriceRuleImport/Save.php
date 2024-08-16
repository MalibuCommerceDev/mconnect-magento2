<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use MalibuCommerce\MConnect\Model\PriceRuleImport\FileProcessor;
use MalibuCommerce\MConnect\Model\PriceRuleImportFactory;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\SavePriceRuleImportEntity;
use Psr\Log\LoggerInterface;

class Save extends AbstractController
{
    /**
     * @var PriceRuleImportFactory
     */
    private PriceRuleImportFactory $priceRuleImportFactory;

    /**
     * @var SavePriceRuleImportEntity
     */
    private SavePriceRuleImportEntity $savePriceRuleImport;

    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param PriceRuleImportFactory $priceRuleImportFactory
     * @param SavePriceRuleImportEntity $savePriceRuleImport
     * @param FileProcessor $fileProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PriceRuleImportFactory $priceRuleImportFactory,
        SavePriceRuleImportEntity $savePriceRuleImport,
        FileProcessor $fileProcessor,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->priceRuleImportFactory = $priceRuleImportFactory;
        $this->savePriceRuleImport = $savePriceRuleImport;
        $this->fileProcessor = $fileProcessor;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $websiteId = $this->getRequest()->getPost('website_id');
            $file = $this->getRequest()->getPost('file');

            $importModel = $this->priceRuleImportFactory->create(['data' => [
                PriceRuleImport::WEBSITE_ID => $websiteId,
                PriceRuleImport::FILENAME => $file[0]['file']
            ]]);

            $this->fileProcessor->moveFileFromTmp($importModel->getFilename());
            $this->savePriceRuleImport->execute($importModel);
            $this->messageManager->addSuccessMessage(__('Price rules file successfully added to the queue.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $this->messageManager->addExceptionMessage($e, __('An error occurred while saving the price rules import.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*');
    }
}
