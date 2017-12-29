<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \MalibuCommerce\MConnect\Model\Queue;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;

class OrderMConnectStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $_orderRepository;

    protected $_queue;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        Queue $queue,
        array $components = [],
        array $data = [])
    {
        $this->_orderRepository = $orderRepository;
        $this->_queue = $queue;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {

                $queueCollection = $this->_queue->getCollection()
                    ->addFilter('code', 'order')
                    ->addFilter('entity_id', $item['entity_id'])
                    ->setOrder('finished_at', 'desc');
                $queue = $queueCollection->getFirstItem();
                $status = $queue->getData('status');

                $order  = $this->_orderRepository->get($item["entity_id"]);
                $navId = $order->getData('nav_id');

                switch ($status) {
                    case "error":
                        $mConnectStatus = '<span title="" style="text-transform: uppercase; font-weight: bold; color: white; font-size: 10px; width: 100%; display: block; text-align: center; border-radius: 10px; background: #ff0000;">error</span>';
                        break;
                    case "success";
                        $mConnectStatus = '<span title="Order exported, NAV ID: '. $navId .'" style="text-transform: uppercase; font-weight: bold; color: white; font-size: 10px; width: 100%; display: block; text-align: center; border-radius: 10px; background: #00c500;">success</span>';
                        break;
                    default:
                        $mConnectStatus = '';
                        break;
                }

                $item[$this->getData('name')] = $mConnectStatus;
            }
        }

        return $dataSource;
    }
}