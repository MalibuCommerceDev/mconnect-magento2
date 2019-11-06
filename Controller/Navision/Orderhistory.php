<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Orderhistory extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order\History
     */
    protected $navOrderHistory;

    /**
     * Orderhistory constructor.
     *
     * @param \Magento\Framework\App\Action\Context                 $context
     * @param \Magento\Customer\Model\Session                       $customerSession
     * @param \Magento\Framework\App\Response\Http                  $httpResponse
     * @param \Magento\Framework\View\Result\PageFactory            $resultPageFactory
     * @param \MalibuCommerce\MConnect\Model\Navision\Order\History $navOrderHistory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MalibuCommerce\MConnect\Model\Navision\Order\History $navOrderHistory
    ) {
        $this->navOrderHistory = $navOrderHistory;
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
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->messageManager->addError($message);
            }
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        } catch (\Throwable $e) {
            $this->messageManager->addException($e, __('NAV orders retrieving error: %1', $e->getMessage()));
            $resultRedirect->setPath('*/*/orderhistory');

            return $resultRedirect;
        }

        return $resultPage;
    }

    /**
     * Retrieve orders from NAV
     *
     * @return array|bool
     */
    protected function getOrders()
    {
        $entities = false;
        if ($this->getRequest()->getParam('search')) {
            $details = array(
                'customer_number' => $this->customerSession->getCustomer()->getNavId(),
            );
            $params = array(
                'date_from'         => 'start_date',
                'date_to'           => 'end_date',
                'po_number_from'    => 'start_po_number',
                'po_number_to'      => 'end_po_number',
                'order_number_from' => 'start_order_number',
                'order_number_to'   => 'end_order_number',
            );
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