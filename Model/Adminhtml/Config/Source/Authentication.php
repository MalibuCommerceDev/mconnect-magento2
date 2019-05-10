<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Source;

class Authentication implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Basic Auth')],
            ['value' => 1, 'label' => __('NTLM')],
            ['value' => 2, 'label' => __('Digest')],
        ];
    }
}
