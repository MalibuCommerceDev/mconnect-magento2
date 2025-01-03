<?php

namespace MalibuCommerce\MConnect\Block\Adminhtml\Order\View;

class Tab extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    protected $_template = 'order/view/tab.phtml';
    private $_coreRegistry;

    public function __construct(
         \Magento\Backend\Block\Template\Context $context,
         \Magento\Framework\Registry $registry,
         array $data = []
    ) {
         $this->_coreRegistry = $registry;
         parent::__construct($context, $data);
    }

    public function getOrder()
    {
         return $this->_coreRegistry->registry('current_order');
    }

    public function getTabLabel()
    {
         return __('M-Connect');
    }

    public function getTabTitle()
    {
         return __('M-Connect');
    }

    public function canShowTab()
    {
         return true;
    }

    public function isHidden()
    {
         return false;
    }
}
