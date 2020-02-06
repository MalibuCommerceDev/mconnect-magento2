<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml;

use MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Pricerule as RuleResourceModel;

class Pricerule extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'mconnect_pricerule';

    protected $_cacheTag = 'mconnect_pricerule';

    protected $_eventPrefix = 'mconnect_pricerule';

    /**
     * Set resource model class
     */
    protected function _construct()
    {
        $this->_init(RuleResourceModel::class);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG];
    }
}
