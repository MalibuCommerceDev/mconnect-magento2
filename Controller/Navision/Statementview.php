<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Statementview extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf
     */
    protected $statementPdf;

    /**
     * Statementview constructor.
     *
     * @param \Magento\Framework\App\Action\Context                 $context
     * @param \Magento\Customer\Model\Session                       $customerSession
     * @param \Magento\Framework\App\Response\Http                  $httpResponse
     * @param \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf
    ) {
        $this->statementPdf = $statementPdf;
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
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $resultRedirect->setPath('*/*/statement');

            return $resultRedirect;
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('NAV statement retrieving error: %1', $e->getMessage()));
            $resultRedirect->setPath('*/*/statement');

            return $resultRedirect;
        }
    }
}