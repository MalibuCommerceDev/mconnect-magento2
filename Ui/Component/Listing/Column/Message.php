<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Message extends \Magento\Ui\Component\Listing\Columns\Column
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
                if (!empty($item['message'])) {
                    $columnData = '<a href="#" onclick="jQuery(\'#messages_' . $item['id'] . '\').toggle(); return false;">' . __('Show/Hide') . '</a>';
                    $columnData .= '<div id="messages_' . $item['id'] . '" style="display: none; font-size: small;"><pre style="white-space: pre-line; word-break: break-word;">' . $item['message'] . '</pre></div>';
                    $item['message'] = $columnData;
                }
            }
        }

        return $dataSource;
    }
}