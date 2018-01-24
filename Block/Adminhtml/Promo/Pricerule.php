<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Promo;

class Pricerule extends \Magento\Backend\Block\Widget\Grid\Container
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'MalibuCommerce_MConnect';
        $this->_controller = 'adminhtml_rules';
        $this->_headerText = __('Mconnect Price Rule');
        //$this->_addButtonLabel = __('Add New Rule');
        parent::_construct();

//        $this->buttonList->add(
//            'apply_rules',
//            [
//                'label' => __('Apply Rules'),
//                'onclick' => "location.href='" . $this->getUrl('catalog_rule/*/applyRules') . "'",
//                'class' => 'apply'
//            ]
//        );
    }

}