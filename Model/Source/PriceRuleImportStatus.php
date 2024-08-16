<?php

namespace MalibuCommerce\MConnect\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MalibuCommerce\MConnect\Model\PriceRuleImport;

class PriceRuleImportStatus implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => PriceRuleImport::STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => PriceRuleImport::STATUS_FAILED,
                'label' => __('Failed')
            ],
            [
                'value' => PriceRuleImport::STATUS_COMPLETE,
                'label' => __('Complete')
            ],
        ];
    }
}
