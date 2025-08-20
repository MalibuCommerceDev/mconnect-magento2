<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport;

use Magento\Backend\App\Action\Context;
use Magento\Framework\File\Size;
use Magento\Framework\Phrase;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractController
{
    /**
     * @var PageFactory
     */
    private PageFactory $resultPageFactory;

    /**
     * @var Size
     */
    private Size $fileSize;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Size $fileSize
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Size $fileSize
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->fileSize = $fileSize;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $this->messageManager->addNoticeMessage($this->getMaxUploadSizeMessage());
        $this->messageManager->addWarningMessage(__('Warning: before importing price rules from the CSV file all existing rules for that website will be removed. To import price rules w/o removal of all existing rules, please use CLI commad "php bin/magento mconnect:import:price-rules"'));

        $resultPage = $this->resultPageFactory->create();
        $resultPage->initLayout();
        $resultPage->getConfig()->getTitle()->prepend(__('M-Connect Price Rules Import'));

        return $resultPage;
    }

    /**
     * Get maximum upload size message
     *
     * @return Phrase
     */
    public function getMaxUploadSizeMessage(): Phrase
    {
        $maxImageSize = $this->fileSize->getMaxFileSizeInMb();
        if ($maxImageSize) {
            $message = __('Make sure your file isn\'t more than %1M.', $maxImageSize);
        } else {
            $message = __('We can\'t provide the upload settings right now.');
        }
        return $message;
    }
}
