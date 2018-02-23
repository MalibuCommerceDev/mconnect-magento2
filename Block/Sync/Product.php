<?php

namespace MalibuCommerce\MConnect\Block\Sync;

class Product extends \Magento\Framework\View\Element\Template
{
    public function getAuth()
    {
        return $this->getRequest()->getParam('auth');
    }

    public function getIdentifier()
    {
        $id = $this->getRequest()->getParam('id');
        return $id;
    }
}