<?php

namespace MalibuCommerce\MConnect\Block\Sync;

class Product extends \Magento\Framework\View\Element\Template
{
    /**
     * Return title
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(__("Sync Product: %s"), $this->getIdentifier());
    }

    /**
     * Return request param auth
     *
     * @return mixed
     */
    public function getAuth()
    {
        return $this->getRequest()->getParam('auth');
    }

    /**
     * Return request param id
     *
     * @return mixed
     */
    public function getIdentifier()
    {
        $id = $this->getRequest()->getParam('id');
        return $id;
    }
}
