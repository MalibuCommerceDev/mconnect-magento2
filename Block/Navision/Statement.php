<?php

namespace MalibuCommerce\MConnect\Block\Navision;

class Statement extends \Magento\Customer\Block\Account\Dashboard
{
    /**
     * @var string
     */
    protected $_template = 'navision/statements.phtml';

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
}
