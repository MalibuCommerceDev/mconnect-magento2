<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Duration extends \Magento\Ui\Component\Listing\Columns\Column
{

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if ($item['duration'] !== null) {
                    $item['duration'] = sprintf('%02dm:%02ds', floor($item['duration'] / 60), $item['duration'] % 60);
                }
            }
        }

        return $dataSource;
    }
}