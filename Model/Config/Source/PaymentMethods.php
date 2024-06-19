<?php

namespace MalibuCommerce\MConnect\Model\Config\Source;

class PaymentMethods extends \Magento\Payment\Model\Config\Source\Allmethods
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $methods = parent::toOptionArray();
        foreach ($methods as $code => &$optionValue) {
            if (is_array($optionValue['value'])) {
                foreach ($optionValue['value'] as &$item) {
                    $item['label'] .= ' (' . $item['value'] . ')';
                }
            } else {
                $optionValue['label'] .= ' (' . $code . ')';
            }
        }

        return $methods;
    }
}
