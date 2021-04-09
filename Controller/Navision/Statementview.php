<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use MalibuCommerce\MConnect\Controller\Navision;
use MalibuCommerce\MConnect\Model\Navision\Statement\Pdf;

class Statementview extends Navision
{
    /**
     * @var Pdf
     */
    protected $statementPdf;

    /**
     * Statementview constructor.
     *
     * @param Context                 $context
     * @param Session                       $customerSession
     * @param Http                  $httpResponse
     * @param Pdf $statementPdf
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Http $httpResponse,
        Pdf $statementPdf
    ) {
        $this->statementPdf = $statementPdf;
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
            $customerNavId = $this->customerSession->getCustomer()->getNavId();
            $startDate     = $this->getRequest()->getParam('date_from');
            $endDate       = $this->getRequest()->getParam('date_to');
            $pdf = $this->statementPdf->get(
                $customerNavId,
                $startDate,
                $endDate
            );
            if ($pdf) {
                $this->displayPdf($pdf, 'statement.pdf');
            } else {
                throw new \Exception('There are no transactions for requested date range');
            }

            return;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError(__('NAV statement retrieving error: %1', $message));
            }
            $resultRedirect->setPath('*/*/statement');

            return $resultRedirect;
        }
    }
}
