<?php
namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class New extends \Magento\Backend\App\Action
{
    /**
     * @return void
     */
    public function execute()
{
    $this->_view->loadLayout();
    $this->_setActiveMenu('MalibuCommerce_MConnect::price_rules');
    $this->_addBreadcrumb(__('Mconnect'), __('New Price Rules'));
    $this->_view->renderLayout();
}
}