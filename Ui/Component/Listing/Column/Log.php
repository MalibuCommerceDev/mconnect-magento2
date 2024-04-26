<?php

namespace MalibuCommerce\MConnect\Ui\Component\Listing\Column;

class Log extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->helper = $helper;
        $this->urlBuilder = $urlBuilder;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        $logsAreInDb = $this->helper->isLogDataToDb();
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if ($logsAreInDb && !empty($item['logs'])) {
                    $logs = $item['logs'];
                } else {
                    $logs = $this->helper->getLogFile($item['id']);
                }

                if ($logs) {
                    $url = $this->urlBuilder->getUrl('mconnect/queue/log', ['id' => $item['id']]);
                    $item['log'] = __('<a target="_blank" href="%1">View</a>', $url);
                } else {
                    $item['log'] = __('N/A');
                }
            }
        }

        return $dataSource;
    }
}
