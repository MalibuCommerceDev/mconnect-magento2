<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Source;

class Authentication implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Basic Auth')],
            ['value' => \MalibuCommerce\MConnect\Model\Config::AUTH_METHOD_NTLM, 'label' => __('NTLM')],
            ['value' => \MalibuCommerce\MConnect\Model\Config::AUTH_METHOD_DIGEST, 'label' => __('Digest')],
            ['value' => \MalibuCommerce\MConnect\Model\Config::AUTH_METHOD_OAUTH2, 'label' => __('OAuth 2.0 / Bearer Token')],
        ];
    }
}
