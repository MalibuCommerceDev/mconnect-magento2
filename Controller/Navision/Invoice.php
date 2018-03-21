<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Invoice extends \MalibuCommerce\MConnect\Controller\Navision
{
    public function execute()
    {
        $this->_view->loadLayout();

        if ($block = $this->_view->getLayout()->getBlock('customer_navision_invoice')) {
//            $block->setRefererUrl($this->_redirect->getRefererUrl());
        }
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Customer Invoices'));
        $this->_view->renderLayout();
    }
}