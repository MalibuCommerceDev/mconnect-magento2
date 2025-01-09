<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Order;

class EditNavId extends \Magento\Backend\App\Action
{
    protected $orderRepository;
    protected $messageManager;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ){
        parent::__construct($context);
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        $post = $this->getRequest()->getPost();
        $order = $this->orderRepository->get($post['order_id']);
        if ($order->getNavId() !== $post['mconnect_nav_id']) {
            $order->setNavId($post['mconnect_nav_id']);
            $order->save();
        }
        $this->messageManager->addSuccess(__(sprintf('Order #%s NAV ID updated', $order->getIncrementId())));
        $this->_redirect($this->_redirect->getRefererUrl() . '#sales_order_view_tabs_malibucommerce_mconnect_order_edit_content');
    }
}
