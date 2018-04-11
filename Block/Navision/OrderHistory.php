<?php

namespace MalibuCommerce\MConnect\Block\Navision;

class OrderHistory extends \Magento\Customer\Block\Account\Dashboard
{
    /**
     * @var string
     */
    protected $_template = 'navision/orders.phtml';

    /**
     * Return request param
     *
     * @param $name
     *
     * @return mixed
     */
    public function getFieldValue($name)
    {
        return $this->getRequest()->getParam($name, '');
    }

    /**
     * @return bool
     */
    public function isSearchInitiated()
    {
        $params = $this->getRequest()->getParams();

        return !empty($params);
    }
}