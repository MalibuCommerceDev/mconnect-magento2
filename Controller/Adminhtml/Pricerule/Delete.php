<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class Delete extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceuleAction
{
    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $model = $this->initRule();
        if ($model->getId()) {
            try {
                $model->delete();
                $this->messageManager->addSuccess(__('Mconnect Price Rule was deleted successfully.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }
        }

        $this->messageManager->addError(__('Can\'t find specified Mconnect Price Rule.'));
        return $resultRedirect->setPath('*/*/');
    }
}
