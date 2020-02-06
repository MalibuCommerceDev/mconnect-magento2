<?php
namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

class Index extends Queue
{
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MalibuCommerce_MConnect::queue');
        $resultPage->getConfig()->getTitle()->prepend(__('Synchronization Queue'));

        return $resultPage;
    }
}
