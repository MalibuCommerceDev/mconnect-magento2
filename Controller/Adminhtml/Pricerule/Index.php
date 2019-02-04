<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class Index extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceruleAction
{
    /**
     * @return void
     */
    public function execute()
    {
        $this->initAction()->_addBreadcrumb(__('Malibu Connect Price Rules'), __('Malibu Connect Price Rules'));
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Malibu Connect Price Rules'));
        $this->_view->renderLayout();
    }
}
