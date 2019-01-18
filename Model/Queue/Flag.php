<?php
namespace MalibuCommerce\MConnect\Model\Queue;

class Flag extends \Magento\Framework\Flag
{
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
