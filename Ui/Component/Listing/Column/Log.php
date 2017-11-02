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
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->helper = $helper;
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $logFile = $this->helper->getLogFile($item['id']);

                if ($logFile) {
                    $label = __('View');
                    $size = $this->helper->getFileSize($logFile);
                    if ($size) {
                        $label = $size;
                    }

                    $item['log'] = '<a target="_blank" href="' . $this->urlBuilder->getUrl('mconnect/queue/log', array('id' => $item['id'])) . '">' . $label . '</a>';
                }
            }
        }

        return $dataSource;
    }
}