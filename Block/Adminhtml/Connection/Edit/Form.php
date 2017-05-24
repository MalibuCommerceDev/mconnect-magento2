<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Connection\Edit;


class Form extends \Magento\Backend\Block\Widget\Form
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Framework\Data\Form
     */
    protected $form;

    public function __construct(
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\Form $form
    ) {
        $this->form = $form;
        $this->registry = $registry;
    }
    /**
     * Prepare form for render
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $entity = $this->registry->registry('current_entity');
        $form = $this->form->create(array(
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
            'enctype'   => 'multipart/form-data'
        ));
        $fieldset = $form->addFieldset('base_fieldset', array('legend' => $this->__('Connection Information')));
        $fieldset->addField('name', 'text', array(
            'name'     => 'name',
            'label'    => $this->__('Name'),
            'title'    => $this->__('Name'),
            'class'    => 'required-entry',
            'required' => true,
        ));
        $fieldset->addField('url', 'text', array(
            'name'     => 'url',
            'label'    => $this->__('URL'),
            'title'    => $this->__('URL'),
            'note'     => $this->__('Including company name and codeunit. Example: http://8.8.8.8:7047/Acme_Co_123/WS/ACME%20CO/Codeunit/Malibu', \Magento\Customer\Model\Group::GROUP_CODE_MAX_LENGTH),
            'class'    => 'required-entry',
            'required' => true,
        ));
        $fieldset->addField('username', 'text', array(
            'name'     => 'username',
            'label'    => $this->__('Username'),
            'title'    => $this->__('Username'),
            'class'    => 'required-entry',
            'required' => true,
        ));
        $fieldset->addField('password', 'password', array(
            'name'     => 'password',
            'label'    => $this->__('Password'),
            'title'    => $this->__('Password'),
            'class'    => 'required-entry',
            'required' => true,
        ));
        $fieldset->addField('sort_order', 'text', array(
            'name'     => 'sort_order',
            'label'    => $this->__('Sort Order'),
            'title'    => $this->__('Sort Order'),
        ));
        $fieldset->addField('rules', 'textarea', array(
            'name'     => 'rules',
            'label'    => $this->__('Rules'),
            'title'    => $this->__('Rules'),
        ));
        if ($entity->getId()) {
            $form->addField('id', 'hidden', array(
                'name' => 'id',
            ));
            $form->addValues($entity->getData());
        }
        $form->setUseContainer(true);
        $form->setId('edit_form');
        $form->setAction($this->getUrl('*/*/save'));
        $this->setForm($form);
    }
}
