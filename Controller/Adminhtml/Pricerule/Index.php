<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class Index extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceuleAction
{
    /**
     * @return void
     */
    public function execute()
    {
        $this->initAction()->_addBreadcrumb(__('Mconnect Price Rules'), __('Mconnect Price Rules'));
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Mconnect Price Rules'));
        $this->_view->renderLayout();
    }
}
