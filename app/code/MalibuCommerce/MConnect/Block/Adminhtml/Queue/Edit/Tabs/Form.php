<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue\Edit\Tabs;


class Form
    extends \Magento\Backend\Block\Widget\Form
{

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $backendSession;

    /**
     * @var \Magento\Framework\Data\Form
     */
    protected $form;

    public function __construct(
        \Magento\Backend\Model\Session $backendSession,
        \Magento\Framework\Data\Form $form
    ) {
        $this->form = $form;
        $this->backendSession = $backendSession;
    }
    protected function _prepareForm()
    {
        $form = $this->form;
        $this->setForm($form);
        $fieldset = $form->addFieldset(
            'malibucommerce_mconnect_queue_form',
            array(
                'legend' => __('Details')
            )
        );
        $fieldset->addField('code', 'text', array(
            'label'    => __('Code'),
            'class'    => 'required-entry',
            'required' => true,
            'name'     => 'code',
        ));
        $fieldset->addField('action', 'text', array(
            'label'    => __('Action'),
            'class'    => 'required-entry',
            'required' => true,
            'name'     => 'action',
        ));
        $fieldset->addField('entity_id', 'text', array(
            'label' => __('Entity ID'),
            'class' => 'validate-digits',
            'name'  => 'entity_id',
        ));
        if ($this->backendSession->getQueueData()) {
            $form->setValues($this->backendSession->getQueueData());
            $this->backendSession->setQueueData(null);
        }
        return parent::_prepareForm();
    }
}
