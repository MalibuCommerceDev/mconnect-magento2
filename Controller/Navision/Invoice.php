<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MalibuCommerce\MConnect\Controller\Navision;

class Invoice extends Navision
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice
     */
    protected $navSalesInvoice;

    /**
     * Invoice constructor.
     *
     * @param Context                 $context
     * @param Session                       $customerSession
     * @param Http                  $httpResponse
     * @param PageFactory            $resultPageFactory
     * @param \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice $navSalesInvoice
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Http $httpResponse,
        PageFactory $resultPageFactory,
        \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice $navSalesInvoice
    ) {
        $this->navSalesInvoice = $navSalesInvoice;
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return Redirect|Page
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $resultPage->getConfig()->getTitle()->set(__('NAV Customer Invoices'));

        try {
            $pageMainTitle = $resultPage->getLayout()->getBlock('page.main.title');
            if ($pageMainTitle) {
                $pageMainTitle->setPageTitle('Customer Invoices');
            }

            $invoices = $this->getInvoices();
            $block = $resultPage->getLayout()->getBlock('customer.navision.invoices.history');
            if ($block) {
                $block->setInvoices($invoices);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError(__('NAV customer invoices retrieving error: %1', $message));
            }
            $resultRedirect->setPath('*/*/invoice');

            return $resultRedirect;
        }

        return $resultPage;
    }

    /**
     * Retrieve invoices from NAV
     *
     * @return array|bool
     * @throws \Exception
     */
    public function getInvoices()
    {
        $entities = false;
        if ($this->getRequest()->getParam('search')) {
            $details = [
                'customer_number' => $this->customerSession->getCustomer()->getNavId(),
            ];
            $params = [
                'date_from'           => 'start_date',
                'date_to'             => 'end_date',
                'po_number_from'      => 'start_po_number',
                'po_number_to'        => 'end_po_number',
                'invoice_number_from' => 'start_invoice_number',
                'invoice_number_to'   => 'end_invoice_number',
            ];
            foreach ($params as $name => $key) {
                $value = $this->getRequest()->getParam($name);
                if ($value) {
                    $details[$key] = $value;
                }
            }
            $entities = $this->navSalesInvoice->get($details);
        }

        return $entities;
    }
}
