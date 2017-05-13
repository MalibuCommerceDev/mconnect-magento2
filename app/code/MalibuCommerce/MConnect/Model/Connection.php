<?php
namespace MalibuCommerce\MConnect\Model;


class Connection extends \Magento\Framework\Model\AbstractModel
{
    protected $_eventPrefix = 'malibucommerce_mconnect_connection';

    protected $_eventObject = 'connection';
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry
    ) {
        parent::__construct(
            $context,
            $registry
        );
    }


    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Connection');
    }
}
