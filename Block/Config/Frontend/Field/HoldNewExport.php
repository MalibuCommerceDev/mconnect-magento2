<?php

namespace MalibuCommerce\MConnect\Block\Config\Frontend\Field;

class HoldNewExport extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \MalibuCommerce\MConnect\Model\Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    protected function _renderValue(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->config->shouldNewOrdersBeForcefullyHolden() && !$this->config->getIsHoldNewOrdersExport()) {
            $element->setComment('<strong>' . __('Will be forcefully holden for %1 minutes, because Signifyd fraud detection is activated', $this->config::DEFAULT_NEW_ORDERS_DELAY) . '</strong>');
        }

        return parent::_renderValue($element);
    }
}
