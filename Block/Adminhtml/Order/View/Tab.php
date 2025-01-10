<?php

namespace MalibuCommerce\MConnect\Block\Adminhtml\Order\View;

class Tab extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * @var string
     */
    protected $_template = 'order/view/tab.phtml';

    /**
     * @var \Magento\Framework\Registry
     */
    private $_coreRegistry;

    /**
     * @param \Magento\Backend\Block\Template\Context
     * @param \Magento\Framework\Registry
     * @param array
     *
     * @return null
     */
    public function __construct(
         \Magento\Backend\Block\Template\Context $context,
         \Magento\Framework\Registry $registry,
         array $data = []
    ) {
         $this->_coreRegistry = $registry;
         parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
         return $this->_coreRegistry->registry('current_order');
    }

    /**
     * @return string
     */
    public function getTabLabel()
    {
         return __('M-Connect');
    }

    /**
     * @return string
     */
    public function getTabTitle()
    {
         return __('M-Connect');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
         return true;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
         return false;
    }
}
