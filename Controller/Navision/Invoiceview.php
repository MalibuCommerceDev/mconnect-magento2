<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use MalibuCommerce\MConnect\Controller\Navision;
use MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf;

class Invoiceview extends Navision
{
    /**
     * @var Pdf
     */
    protected $invoicePdf;

    /**
     * Invoiceview constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param Http    $httpResponse
     * @param Pdf     $invoicePdf
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Http $httpResponse,
        Pdf $invoicePdf
    ) {
        $this->invoicePdf = $invoicePdf;
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
            $pdf = $this->invoicePdf->get(
                $number,
                $customerNavId
            );
            if ($pdf) {
                $this->displayPdf($pdf, 'invoice_' . $number . '.pdf');
            } else {
                throw new \Exception('Requested invoice can\'t be found');
            }

            return;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError(__('NAV invoices retrieving error: %1', $message));
            }
            $resultRedirect->setPath('*/*/invoice');

            return $resultRedirect;
        }
    }
}
