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

                $status = $this->getStatusHtml($queue);
                $item[$this->getData('name')] = $status;
            }
        }

        return $dataSource;
    }

    /**
     * Return Queue Status in html
     *
     * @param Queue $queue
     * @return string
     */
    public function getStatusHtml(\MalibuCommerce\MConnect\Model\Queue $queue)
    {
        $result = '';
        $status = $queue->getStatus();
        $style = 'text-transform: uppercase;'
            .' font-weight: bold;'
            .' color: white;'
            .' font-size: 10px;'
            .' width: 100%;'
            .' display: block;'
            .' text-align: center;'
            .' border-radius: 10px;'
        ;
        $title = htmlentities($queue->getMessage());
        $background = false;
        switch ($status) {
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING:
                $background = '#9a9a9a';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_RUNNING:
                $background = '#28dade';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS:
                $background = '#00c500';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_ERROR:
                $background = '#ff0000';
                break;
            default:
                $result = $status;
        }
        if ($background) {
            $result = '<span title="' . $title . '" style="' . $style . ' background: ' . $background . ';">' . $status . '</span>';
        }
        return $result;
    }
}