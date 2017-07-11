<?php
namespace MalibuCommerce\MConnect\Model\Queue;

class Flag extends \Magento\Framework\Flag
{
    const FLAG_CODE_LAST_PRODUCT_SYNC_TIME = 'malibucommerce_mconnect_product_sync_time';
    const FLAG_CODE_LAST_CUSTOMER_SYNC_TIME = 'malibucommerce_mconnect_customer_sync_time';

    /**
     * Setter for flag code
     *
     * @param string $code
     *
     * @return $this
     */
    public function setQueueFlagCode($code)
    {
        $this->_flagCode = $code;
        return $this;
    }
}
