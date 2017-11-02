<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

class Sync extends Queue
{
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $result = $this->mConnectCronQueue->process();
        $this->messageManager->addSuccessMessage(__($result));

        return $resultRedirect->setPath('*/*/');
    }
}