<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml;


class Queue extends \Magento\Backend\Block\Widget\Grid\Container
{
    public function __construct(\Magento\Backend\Block\Widget\Context $context)
    {
        $this->_controller     = 'adminhtml_queue';
        $this->_blockGroup     = 'malibucommerce_mconnect';
        $this->_headerText     = __('Synchronization Queue');
        parent::__construct($context);
        $this->_addButton('sync', array(
                'label'   => $this->__('Sync All Pending Now'),
                'onclick' => "setLocation('{$this->getUrl('*/*/sync')}')",
                'class'   => 'success'
        ));
    }
}
