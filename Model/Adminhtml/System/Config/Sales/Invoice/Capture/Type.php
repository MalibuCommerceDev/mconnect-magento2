<?php
namespace MalibuCommerce\MConnect\Model\Adminhtml\System\Config\Sales\Invoice\Capture;

class Type
{
    public function toOptionArray()
    {
        return [
            ['value' => \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE, 'label' => __('Online')],
            ['value' => \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE, 'label' => __('Offline')],
            ['value' => \Magento\Sales\Model\Order\Invoice::NOT_CAPTURE, 'label' => __('Do not Capture')],
        ];
    }
}
