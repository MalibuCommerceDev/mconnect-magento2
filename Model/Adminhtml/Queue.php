<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml;

class Queue extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'mconnect_queue';

    protected $_cacheTag = 'mconnect_queue';

    protected $_eventPrefix = 'mconnect_queue';

    protected function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Adminhtml\Queue');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}