<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Title extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * @var \Magento\Sales\Api\CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    protected $customers = [];
    protected $products = [];
    protected $orders = [];
    protected $creditmemos = [];

    /**
     * Entity constructor
     *
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory           $uiComponentFactory
     * @param \Magento\Framework\UrlInterface                              $urlBuilder
     * @param \Magento\Customer\Model\CustomerFactory                      $customerFactory
     * @param \Magento\Catalog\Model\ProductFactory                        $catalogProductFactory
     * @param \Magento\Sales\Model\OrderFactory                            $salesOrderFactory
     * @param \Magento\Sales\Api\CreditmemoRepositoryInterface             $creditmemoRepository,
     * @param array                                                        $components
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->customerFactory = $customerFactory;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->creditmemoRepository = $creditmemoRepository;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Decorate Entity grid column data
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as & $item) {
            if (empty($item['title'])) {
                continue;
            }

            $link = false;
            $title = false;
            if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Customer::CODE) {
                if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {
                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('customer/index/edit', array('id' => $item['entity_id']));
                        $title = $item['title'];
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Product::CODE) {
                if ($item['action'] === 'import_single') {
                    if (!array_key_exists($item['title'], $this->products)) {
                        $this->products[$item['title']] = $this->catalogProductFactory->create()->load($item['title']);
                    }

                    $entity = $this->products[$item['title']];
                    if ($entity->getId()) {
                        $link = $this->urlBuilder->getUrl('catalog/product/edit', array('id' => $item['title']));
                        $title = $entity->getName();
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Order::CODE) {if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {

                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('sales/order/view', array('order_id' => $item['entity_id']));
                        $title = '#' . $item['title'];
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Creditmemo::CODE) {
                if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {
                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('sales/creditmemo/view', array('creditmemo_id' => $item['entity_id']));
                        $title = '#' . $item['entity_id'];
                    }
                }
            }
            if ($link !== false) {
                $item['title'] = sprintf('<a href="%s" target="_blank" title="%s">%s<a/>', $link, $title ? $title : $item['title'], $title ? $title : $item['title']);
            }
        }

        return $dataSource;
    }
}