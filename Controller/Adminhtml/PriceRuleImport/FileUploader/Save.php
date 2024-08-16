<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport\FileUploader;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport\AbstractController;
use MalibuCommerce\MConnect\Model\PriceRuleImport\FileProcessor;

class Save extends AbstractController
{
    /**
     * @var FileProcessor
     */
    private FileProcessor $fileProcessor;

    /**
     * @param Context $context
     * @param FileProcessor $fileProcessor
     */
    public function __construct(
        Context $context,
        FileProcessor $fileProcessor
    ) {
        parent::__construct($context);
        $this->fileProcessor = $fileProcessor;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->fileProcessor->saveFileToTmpDir(key($_FILES));
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }
}
