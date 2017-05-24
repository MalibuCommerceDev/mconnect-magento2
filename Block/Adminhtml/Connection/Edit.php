<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Connection;


class Edit extends \Magento\Backend\Block\Widget\Form\Container
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry
    )
    {
        $this->registry = $registry;
        parent::__construct($context);

        $this->_blockGroup = 'malibucommerce_mconnect';
        $this->_controller = 'adminhtml_connection';
        $this->_objectId   = 'id';

        if (!$this->registry->registry('current_entity')->getId()) {
            $this->_removeButton('delete');
        }
        $this->_addButton('save_and_continue', array(
            'label'     => $this->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save'
        ), 10);

        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                editForm.submit($('edit_form').action + 'back/edit/');
            }
        ";
    }

    public function getHeaderText()
    {
        if(!is_null($this->registry->registry('current_entity')->getId())) {
            return $this->__('Edit Connection "%s"', $this->escapeHtml($this->registry->registry('current_entity')->getName()));
        } else {
            return $this->__('New Connection');
        }
    }
}
