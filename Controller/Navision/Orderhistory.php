<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Orderhistory extends \MalibuCommerce\MConnect\Controller\Navision
{
    public function execute()
    {
        $this->_view->loadLayout();

//        if ($block = $this->_view->getLayout()->getBlock('customer_navision_orderhistory')) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
//        }
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Order History'));
        $this->_view->renderLayout();
    }
}