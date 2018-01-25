<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

abstract class PriceruleAction extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'MalibuCommerce_MConnect::price_rule';

    /**
     * @var bool|\Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry = null;

    /**
     * Price Rules constructor.
     *
     * @param \Magento\Backend\App\Action\Context        $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $coreRegistry
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->coreRegistry = $coreRegistry;
    }

    /**
     * Initiate rule
     *
     * @return \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected function initRule()
    {
        $this->coreRegistry->register(
            'current_mconnect_rule',
            $this->_objectManager->create('MalibuCommerce\MConnect\Model\Pricerule')
        );
        $id = (int)$this->getRequest()->getParam('id');

        if ($id) {
            $this->coreRegistry->registry('current_mconnect_rule')->load($id);
        }

        return $this->coreRegistry->registry('current_mconnect_rule');
    }

    /**
     * Initiate action
     *
     * @return $this
     */
    protected function initAction()
    {
        $this->_view->loadLayout();
        $this->_setActiveMenu('MalibuCommerce_MConnect::price_rule')
            ->_addBreadcrumb(__('Mconnect'), __('Mconnect'))
            ->_addBreadcrumb(__('Promotions'), __('Promotions'));

        return $this;
    }
}
