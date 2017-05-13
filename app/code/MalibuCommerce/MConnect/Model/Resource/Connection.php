<?php
namespace MalibuCommerce\MConnect\Model\Resource;

class Connection extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    public function _construct()
    {
        $this->_init('malibucommerce_mconnect_connection', 'id');
    }

    // protected function _beforeSave(Mage_Core_Model_Abstract $object)
    // {
    //     return parent::_beforeSave();
    // }

    // protected function _afterLoad(Mage_Core_Model_Abstract $object)
    // {
    //     return parent::_afterLoad();
    // }
}
