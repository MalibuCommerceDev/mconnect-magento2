<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use MalibuCommerce\MConnect\Model\Queue;

class Status extends \Magento\Ui\Component\Listing\Columns\Column
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
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $style = 'text-transform: uppercase;'
                 .' font-weight: bold;'
                 .' color: white;'
                 .' font-size: 10px;'
                 .' width: 100%;'
                 .' padding: 1px 2px;'
                 .' display: block;'
                 .' text-align: center;'
                 .' border-radius: 10px;';

        foreach ($dataSource['data']['items'] as & $item) {
            if (empty($item['status'])) {
                continue;
            }

            switch ($item['status']) {
                case Queue::STATUS_PENDING:
                    $result = '<span style="' . $style . ' background: #9a9a9a;">' . $item['status'] . '</span>';
                    break;
                case Queue::STATUS_RUNNING:
                    $result = '<span style="' . $style . ' background: #de890a;">' . $item['status'] . '</span>';
                    break;
                case Queue::STATUS_WARNING:
                    $result = '<span style="' . $style . ' background: #ff7373;">' . $item['status'] . '</span>';
                    break;
                case Queue::STATUS_SUCCESS:
                    $result = '<span style="' . $style . ' background: #00c500;">' . $item['status'] . '</span>';
                    break;
                case Queue::STATUS_ERROR:
                    $result = '<span style="' . $style . ' background: #ff0000;">' . $item['status'] . '</span>';
                    break;
                case Queue::STATUS_CANCELED:
                    $result = '<span style="' . $style . ' background: #6c8cd5;">' . $item['status'] . '</span>';
                    break;
                default:
                    $result = $item['status'];
            }
            $item['status'] = $result;
        }

        return $dataSource;
    }
}