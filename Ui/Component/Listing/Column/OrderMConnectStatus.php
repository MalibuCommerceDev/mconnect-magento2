<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;

class OrderMConnectStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $_orderRepository;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        array $components = [],
        array $data = [])
    {
        $this->_orderRepository = $orderRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {

                $order  = $this->_orderRepository->get($item["entity_id"]);
                $status = $order->getData('mconnect_status');
                $navId = $order->getData('nav_id');

                switch ($status) {
                    case "0":
                        //$export_status = "No";
                        $export_status = '<span title="" style="text-transform: uppercase; font-weight: bold; color: white; font-size: 10px; width: 100%; display: block; text-align: center; border-radius: 10px; background: #ff0000;">error</span>';
                        break;
                    case "1";
                        //$export_status = "Yes";
                        $export_status = '<span title="Order exported, NAV ID: '. $navId .'" style="text-transform: uppercase; font-weight: bold; color: white; font-size: 10px; width: 100%; display: block; text-align: center; border-radius: 10px; background: #00c500;">success</span>';
                        break;
                    default:
                        //$export_status = "Failed";
                        $export_status = '';
                        break;
                }

                $item[$this->getData('name')] = $export_status;
            }
        }

        return $dataSource;
    }
}