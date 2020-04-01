<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class Index extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceruleAction
{
    /**
     * @return void
     */
    public function execute()
    {
        $this->initAction()->_addBreadcrumb(__('M-Connect Price Rules'), __('M-Connect Price Rules'));
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('M-Connect Price Rules'));
        $this->_view->renderLayout();
    }
}
