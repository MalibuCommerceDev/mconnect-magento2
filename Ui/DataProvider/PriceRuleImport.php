<?php

namespace MalibuCommerce\MConnect\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use MalibuCommerce\MConnect\Model\ResourceModel\PriceRuleImport\CollectionFactory;

class PriceRuleImport extends AbstractDataProvider
{
    /**
     * @param $name
     * @param $primaryFieldName
     * @param $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
