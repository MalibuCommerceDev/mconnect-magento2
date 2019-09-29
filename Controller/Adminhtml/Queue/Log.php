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
        $logFile = $this->helper->getLog($queueItemId);
        if (!$logFile) {
            $this->messageManager->addErrorMessage(__('Wrong request.'));
            return $resultRedirect->setPath('*/*');
        }

        $size = $this->helper->getLogSize($logFile, false);
        if ($size > \MalibuCommerce\MConnect\Helper\Data::ALLOWED_LOG_SIZE_TO_BE_VIEWED) {
            $this->messageManager->addErrorMessage(__('Wrong request'));
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