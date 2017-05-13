<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml;


class Connection extends \Magento\Backend\Block\Widget\Grid\Container
{
    public function __construct(\Magento\Backend\Block\Widget\Context $context)
    {
        $this->_controller     = 'adminhtml_connection';
        $this->_blockGroup     = 'malibucommerce_mconnect';
        $this->_headerText     = __('Connection Manager');
        parent::__construct($context);
    }
}
