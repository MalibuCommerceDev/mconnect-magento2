<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml;

class Pricerule extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'mconnect_pricerule';

    protected $_cacheTag = 'mconnect_pricerule';

    protected $_eventPrefix = 'mconnect_pricerule';

    protected function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Adminhtml\Pricerule');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}