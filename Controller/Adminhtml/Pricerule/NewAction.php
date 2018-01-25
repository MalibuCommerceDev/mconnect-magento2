<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class NewAction extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceuleAction
{
    /**
     * Create new Mconnect Price Rule
     *
     * @return void
     */
    public function execute()
    {
        $this->_forward('edit');
    }
}