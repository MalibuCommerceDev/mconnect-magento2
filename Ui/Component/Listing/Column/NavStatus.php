<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \MalibuCommerce\MConnect\Model\Queue;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;

class NavStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        Queue $queue,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        array $components = [],
        array $data = [])
    {
        $this->helper = $helper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        $targetColumnName = $this->getData('name');
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!array_key_exists($targetColumnName, $item)) {
                    continue;
                }
                $item[$targetColumnName] = $this->helper->getQueueItemStatusHtml($item['mc_status'], $item['mc_message']);
            }
        }

        return $dataSource;
    }

}