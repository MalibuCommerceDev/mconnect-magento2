<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store as SystemStore;

class Websites implements OptionSourceInterface
{
    /**
     * @var SystemStore
     */
    private SystemStore $systemStore;

    /**
     * @param SystemStore $systemStore
     */
    public function __construct(
        SystemStore $systemStore
    ) {
        $this->systemStore = $systemStore;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return $this->systemStore->getWebsiteValuesForForm(true);
    }
}
