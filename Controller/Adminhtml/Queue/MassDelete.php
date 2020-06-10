<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;

class MassDelete extends AbstractMassAction
{
    /**
     * @param AbstractCollection $collection
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    protected function massAction(AbstractCollection $collection)
    {
        $processedItems = $this->mConnectQueue->deleteQueueItemById($collection->getAllIds());

        if ($processedItems) {
            $this->messageManager->addSuccess(__('A total of %1 item(s) were removed.', $processedItems));
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($this->redirectUrl);

        return $resultRedirect;
    }
}
