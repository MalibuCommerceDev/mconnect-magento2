<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Website extends \Magento\Ui\Component\Listing\Columns\Column
{
    const DEFAULT_WEBSITE_ID = 1;

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

    protected $websites = [];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    protected $websiteFactory;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\WebsiteFactory $websiteFactory,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->websiteFactory = $websiteFactory;

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
            if (!isset($item['website_id'])) {
                continue;
            }

            if (empty($item['website_id'])) {
                $item['website_id'] = self::DEFAULT_WEBSITE_ID;
            }

            if (!array_key_exists($item['website_id'], $this->websites)) {
                $website = $this->websiteFactory->create()->load($item['website_id']);
                if ($website && $website->getId()) {
                    $link = $this->urlBuilder->getUrl('admin/system_store/editWebsite', ['website_id' => $item['website_id']]);
                    $this->websites[$item['website_id']] = sprintf('<a href="%s" target="_blank" title="%s">%s<a/> (ID: %s; Code: %s)', $link, $website->getName(), $website->getName(), $website->getId(), $website->getCode());
                }
            }
            if (empty($this->websites[$item['website_id']])) {
                continue;
            }

            $item['website_id'] = $this->websites[$item['website_id']];

        }

        return $dataSource;
    }
}
