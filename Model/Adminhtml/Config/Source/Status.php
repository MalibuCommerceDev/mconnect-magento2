<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Source;

use MalibuCommerce\MConnect\Model\Queue;

class Status implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => Queue::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => Queue::STATUS_SUCCESS, 'label' => __('Success')],
            ['value' => Queue::STATUS_RUNNING, 'label' => __('Running')],
            ['value' => Queue::STATUS_ERROR, 'label' => __('Error')],
            ['value' => Queue::STATUS_CANCELED, 'label' => __('Canceled')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            Queue::STATUS_PENDING  => __('Pending'),
            Queue::STATUS_SUCCESS  => __('Success'),
            Queue::STATUS_RUNNING  => __('Running'),
            Queue::STATUS_ERROR    => __('Error'),
            Queue::STATUS_CANCELED => __('Canceled'),
        ];
    }
}
