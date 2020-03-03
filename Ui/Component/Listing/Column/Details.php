<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Details extends \Magento\Ui\Component\Listing\Columns\Column
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
                if (!empty($item['details'])) {
                    $html = '';
                    if ($item['details'] && $values = json_decode(html_entity_decode($item['details']))) {
                        foreach ($values as $key => $value) {
                            $html .= sprintf('<strong>%s</strong>: %s<br />', ucwords($key, ' '), $value);
                        }
                    }
                    $item['details'] = $html;
                }
            }
        }

        return $dataSource;
    }
}
