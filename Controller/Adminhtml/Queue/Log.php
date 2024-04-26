<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

class Log extends Queue
{
    public function execute()
    {
        if (!$this->mConnectQueue->getConfig()->isModuleEnabled()) {
            $this->_forward('defaultIndex');
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $queueItemId = (int)$this->getRequest()->getParam('id');
        $logs = $this->helper->getLog($queueItemId);
        if (!$logs) {
            $this->messageManager->addErrorMessage(__('No recorded logs'));
            return $resultRedirect->setPath('*/*');
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MalibuCommerce_MConnect::queue');
        $resultPage->getConfig()->getTitle()->prepend(__('Synchronization Queue'));
        $resultPage->getConfig()->getTitle()->prepend(__('Mconnect Queue Item Log'));

        return $resultPage;
    }
}
