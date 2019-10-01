<?php

namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue;

class Log extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    /**
     * Log constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \MalibuCommerce\MConnect\Helper\Data             $helper
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        array $data = []
    ) {
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    public function getLogContents()
    {
        return $this->helper->getLogContents((int)$this->getRequest()->getParam('id'));
    }

    public function getLogDetails()
    {
        return $this->helper->getLogContents((int)$this->getRequest()->getParam('id'), false);
    }

    /**
     * Retrieve back button url
     *
     * @return string
     */
    public function getBackUrl()
    {
        return $this->getUrl('mconnect/queue/index');
    }
}
