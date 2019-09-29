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
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $logFile = $this->helper->getLog($item['id']);
                $size = $this->helper->getLogSize($logFile, false);

                if ($size) {
                    if ($size <= \MalibuCommerce\MConnect\Helper\Data::ALLOWED_LOG_SIZE_TO_BE_VIEWED) {
                        $url = $this->urlBuilder->getUrl('mconnect/queue/log', ['id' => $item['id']]);
                        $item['log'] = __('<a target="_blank" href="%1">View (%2)</a>', $url, $this->helper->getLogSize($logFile));
                    } else {
                        $item['log'] = __('Large Data (%1)', $size);
                    }
                } else {
                    $item['log'] = __('N/A');
                }
            }
        }

        return $dataSource;
    }
}