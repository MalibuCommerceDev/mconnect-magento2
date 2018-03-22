<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Invoice extends \MalibuCommerce\MConnect\Controller\Navision
{
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Customer Invoices'));
        $this->_view->renderLayout();
    }
}