<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;

class MassSyncNow extends AbstractMassAction
{
    /**
     * @param AbstractCollection $collection
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    protected function massAction(AbstractCollection $collection)
    {
        $processedItems = 0;
        foreach ($collection->getAllIds() as $queueItemId) {
            $this->mConnectQueue->load($queueItemId)->process();
            $processedItems++;
        }

        if ($processedItems) {
            $this->messageManager->addSuccess(__('A total of %1 item(s) were synced.', $processedItems));
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($this->redirectUrl);

        return $resultRedirect;
    }
}
