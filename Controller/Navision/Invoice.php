<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Invoice extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice
     */
    protected $navSalesInvoice;

    /**
     * Invoice constructor.
     *
     * @param \Magento\Framework\App\Action\Context                 $context
     * @param \Magento\Customer\Model\Session                       $customerSession
     * @param \Magento\Framework\App\Response\Http                  $httpResponse
     * @param \Magento\Framework\View\Result\PageFactory            $resultPageFactory
     * @param \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice $navSalesInvoice
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MalibuCommerce\MConnect\Model\Navision\Sales\Invoice $navSalesInvoice
    ) {
        $this->navSalesInvoice = $navSalesInvoice;
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
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
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $resultRedirect->setPath('*/*/invoice');

            return $resultRedirect;
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('NAV customer invoices retrieving error: %1', $e->getMessage()));
            $resultRedirect->setPath('*/*/invoice');

            return $resultRedirect;
        }

        return $resultPage;
    }

    /**
     * Retrieve invoices from NAV
     *
     * @return array|bool
     */
    public function getInvoices()
    {
        $entities = false;
        if ($this->getRequest()->getParam('search')) {
            $details = array(
                'customer_number' => $this->customerSession->getCustomer()->getNavId(),
            );
            $params = array(
                'date_from'           => 'start_date',
                'date_to'             => 'end_date',
                'po_number_from'      => 'start_po_number',
                'po_number_to'        => 'end_po_number',
                'invoice_number_from' => 'start_invoice_number',
                'invoice_number_to'   => 'end_invoice_number',
            );
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