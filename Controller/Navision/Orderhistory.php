<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use MalibuCommerce\MConnect\Controller\Navision;
use MalibuCommerce\MConnect\Model\Navision\Order\History;

class Orderhistory extends Navision
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var History
     */
    protected $navOrderHistory;

    /**
     * Orderhistory constructor.
     *
     * @param Context                 $context
     * @param Session                       $customerSession
     * @param Http                  $httpResponse
     * @param PageFactory            $resultPageFactory
     * @param History $navOrderHistory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Http $httpResponse,
        PageFactory $resultPageFactory,
        History $navOrderHistory
    ) {
        $this->navOrderHistory = $navOrderHistory;
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context, $customerSession, $httpResponse);
    }

    /**
     * @return Redirect|Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $resultPage->getConfig()->getTitle()->set(__('NAV Orders'));

        try {
            $pageMainTitle = $resultPage->getLayout()->getBlock('page.main.title');
            if ($pageMainTitle) {
                $pageMainTitle->setPageTitle('Orders History');
            }

            $orders = $this->getOrders();
            $block = $resultPage->getLayout()->getBlock('customer.navision.orders.history');
            if ($block) {
                $block->setOrders($orders);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError(__('NAV orders retrieving error: %1', $message));
            }
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        }

        return $resultPage;
    }

    /**
     * Retrieve orders from NAV
     *
     * @return array|bool
     * @throws \Exception
     */
    protected function getOrders()
    {
        $entities = false;
        if ($this->getRequest()->getParam('search')) {
            $details = [
                'customer_number' => $this->customerSession->getCustomer()->getNavId(),
            ];
            $params = [
                'date_from'         => 'start_date',
                'date_to'           => 'end_date',
                'po_number_from'    => 'start_po_number',
                'po_number_to'      => 'end_po_number',
                'order_number_from' => 'start_order_number',
                'order_number_to'   => 'end_order_number',
            ];
            foreach ($params as $name => $key) {
                $value = $this->getRequest()->getParam($name);
                if ($value) {
                    $details[$key] = $value;
                }
            }
            $entities = $this->navOrderHistory->get($details);
        }

        return $entities;
    }
}
