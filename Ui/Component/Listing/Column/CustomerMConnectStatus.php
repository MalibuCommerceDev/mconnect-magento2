<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \MalibuCommerce\MConnect\Model\Queue;
use \Magento\Customer\Api\Data\CustomerInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;

class CustomerMConnectStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    protected $_queue;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Queue $queue,
        array $components = [],
        array $data = [])
    {
        $this->_queue = $queue;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {

                $queueCollection = $this->_queue->getCollection()
                    ->addFilter('code', 'customer')
                    ->addFilter('entity_id', $item['entity_id'])
                    ->setOrder('finished_at', 'desc');
                $queue = $queueCollection->getFirstItem();

                $status = $queue->getData('status');
                $navId = $item['nav_id'];

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