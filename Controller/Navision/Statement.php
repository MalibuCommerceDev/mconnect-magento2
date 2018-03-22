<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Statement extends \MalibuCommerce\MConnect\Controller\Navision
{
    public function execute()
    {
        $this->_view->loadLayout();
        $this->_view->getPage()->getConfig()->getTitle()->set(__('Customer Statements'));
        $this->_view->renderLayout();
    }
}