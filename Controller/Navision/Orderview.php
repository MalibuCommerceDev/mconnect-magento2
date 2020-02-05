<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Orderview extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order\Pdf
     */
    protected $orderPdf;

    /**
     * Orderview constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Customer\Model\Session                   $customerSession
     * @param \Magento\Framework\App\Response\Http              $httpResponse
     * @param \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf
    ) {
        $this->orderPdf = $orderPdf;
        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect|void
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $number = $this->getRequest()->getParam('number');
            $customerNavId = $this->customerSession->getCustomer()->getNavId();
            $pdf = $this->orderPdf->get(
                $number,
                $customerNavId
            );
            if ($pdf) {
                $this->displayPdf($pdf, $number . '.pdf');
            } else {
                throw new \Exception('Requested order can\'t be found');
            }

            return;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        } catch (\Throwable $e) {
            $this->messageManager->addException($e, __('NAV order retrieving error: %1', $e->getMessage()));
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        }
    }
}
