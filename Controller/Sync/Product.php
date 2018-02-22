<?php

namespace MalibuCommerce\MConnect\Controller\Sync;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Product extends Action
{
    /** @var  \Magento\Framework\View\Result\Page */
    protected $resultPageFactory;

    public function __construct(
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $resultPage->getConfig()->setPageLayout('1column');
        $resultPage->getLayout()->getUpdate();

        $resultPage->getConfig()->getTitle()->set(__('Product Sync'));
//        $resultPage->addHandle('mconnect_sync_product');
        return $resultPage;
//        echo "AA";
    }
}