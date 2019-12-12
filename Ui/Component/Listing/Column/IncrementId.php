<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class IncrementId extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;


    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    protected $products = [];

    /**
     * Entity constructor
     *
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory           $uiComponentFactory
     * @param \Magento\Framework\UrlInterface                              $urlBuilder
     * @param \Magento\Catalog\Model\ProductFactory                        $catalogProductFactory
     * @param array                                                        $components
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->catalogProductFactory = $catalogProductFactory;

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
            if (empty($item['entity_increment_id'])) {
                continue;
            }

            $link = false;
            $title = false;
            if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Customer::CODE) {
                if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {
                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('customer/index/edit', array('id' => $item['entity_id']));
                        $title = $item['entity_increment_id'];
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Product::CODE) {
                if ($item['action'] === 'import_single') {
                    if (!array_key_exists($item['entity_id'], $this->products)) {
                        $this->products[$item['entity_id']] = $this->catalogProductFactory->create()->load($item['entity_id']);
                    }

                    $entity = $this->products[$item['entity_id']];
                    if ($entity->getId()) {
                        $link = $this->urlBuilder->getUrl('catalog/product/edit', array('id' => $item['entity_id']));
                        $title = $entity->getName();
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Order::CODE) {if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {

                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('sales/order/view', array('order_id' => $item['entity_id']));
                        $title = '#' . $item['entity_increment_id'];
                    }
                }
            } else if ($item['code'] === \MalibuCommerce\MConnect\Model\Queue\Creditmemo::CODE) {
                if ($item['action'] === \MalibuCommerce\MConnect\Model\Queue::ACTION_EXPORT) {
                    if ($item['entity_id']) {
                        $link = $this->urlBuilder->getUrl('sales/creditmemo/view', array('creditmemo_id' => $item['entity_id']));
                        $title = '#' . $item['entity_increment_id'];
                    }
                }
            }
            if ($link !== false) {
                $item['entity_increment_id'] = sprintf('<a href="%s" target="_blank" title="%s">%s<a/>', $link, $title ? $title : $item['entity_increment_id'], $title ? $title : $item['entity_increment_id']);
            }
        }

        return $dataSource;
    }
}