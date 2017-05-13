<?php
namespace MalibuCommerce\MConnect\Model;


class Lastsync extends \Magento\Framework\Model\AbstractModel
{
    protected $_eventPrefix = 'malibucommerce_mconnect_last_sync';

    protected $_eventObject = 'last_sync';
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
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Lastsync');
    }
}
