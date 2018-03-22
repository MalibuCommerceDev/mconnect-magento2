<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Orderhistory extends \MalibuCommerce\MConnect\Controller\Navision
{
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Order History'));
        $this->_view->renderLayout();
    }
}