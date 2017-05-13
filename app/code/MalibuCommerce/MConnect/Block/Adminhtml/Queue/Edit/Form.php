<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue\Edit;


class Form extends \Magento\Backend\Block\Widget\Form
{

    /**
     * @var \Magento\Framework\Data\Form
     */
    protected $form;

    public function __construct(
        \Magento\Framework\Data\Form $form
    ) {
        $this->form = $form;
    }
    protected function _prepareForm()
    {
        $this->setForm($this->form->create(array(
            'id'            => 'edit_form',
            'action'        => $this->getUrl('*/*/save', array('id' => $this->getRequest()->getParam('id'))),
            'method'        => 'post',
            'enctype'       => 'multipart/form-data',
            'use_container' => true
        )));
        return parent::_prepareForm();
    }
}
