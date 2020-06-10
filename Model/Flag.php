<?php
namespace MalibuCommerce\MConnect\Model;

class Flag extends \Magento\Framework\Flag
{
    /**
     * Setter for flag code
     *
     * @param string $code
     *
     * @return $this
     */
    public function setMconnectFlagCode($code)
    {
        $this->_flagCode = $code;
        return $this;
    }
}
