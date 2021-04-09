<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use MalibuCommerce\MConnect\Controller\Navision;
use MalibuCommerce\MConnect\Model\Navision\Order\Pdf;

class Orderview extends Navision
{
    /**
     * @var Pdf
     */
    protected $orderPdf;

    /**
     * Orderview constructor
     *
     * @param Context $context
     * @param Session       $customerSession
     * @param Http  $httpResponse
     * @param Pdf                                   $orderPdf
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        sHttp $httpResponse,
        Pdf $orderPdf
    ) {
        $this->orderPdf = $orderPdf;
        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return Redirect|void
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
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
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError(__('NAV order retrieving error: %1', $message));
            }
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        }
    }
}
