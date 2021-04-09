<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\View\Result\PageFactory;
use MalibuCommerce\MConnect\Controller\Navision;

class Statement extends Navision
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Statement constructor.
     *
     * @param Context      $context
     * @param Session            $customerSession
     * @param Http       $httpResponse
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Http $httpResponse,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        $resultPage->getConfig()->getTitle()->set(__('NAV Customer Statement'));

        $pageMainTitle = $resultPage->getLayout()->getBlock('page.main.title');
        if ($pageMainTitle) {
            $pageMainTitle->setPageTitle('Customer Statement');
        }

        return $resultPage;
    }
}
