<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

use \MalibuCommerce\MConnect\Model\Queue;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;

class CustomerMConnectStatus extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var Queue
     */
    protected $_queue;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Queue $queue,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        array $components = [],
        array $data = [])
    {
        $this->_queue = $queue;
        $this->helper = $helper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                /** @var \MalibuCommerce\MConnect\Model\Resource\Queue\Collection $queueCollection */
                $queueCollection = $this->_queue->getCollection()
                    ->addFilter('code', 'customer')
                    ->addFilter('entity_id', $item['entity_id'])
                    ->setPageSize(1)
                    ->setCurPage(1)
                    ->setOrder('finished_at', 'desc');

                /** @var \MalibuCommerce\MConnect\Model\Queue $queueItem */
                $queueItem = $queueCollection->getFirstItem();
                $status = $this->helper->getQueueItemStatusHtml($queueItem);
                $item[$this->getData('name')] = $status;
            }
        }

        return $dataSource;
    }
}