<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue;


class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    public function __construct(\Magento\Backend\Block\Widget\Context $context)
    {
        parent::__construct($context);
        $this->_objectId   = 'id';
        $this->_blockGroup = 'malibucommerce_mconnect';
        $this->_controller = 'adminhtml_queue';
    }

    public function getHeaderText()
    {
        return __('Add Item');
    }
}
