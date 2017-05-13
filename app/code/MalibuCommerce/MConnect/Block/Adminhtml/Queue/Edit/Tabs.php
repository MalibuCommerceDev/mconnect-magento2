<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue\Edit;


class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Backend\Model\Auth\Session $authSession
    ) {
        parent::__construct($context, $jsonEncoder, $authSession);
        $this->setId('queue_tabs')
            ->setDestElementId('edit_form')
            ->setTitle(__('Details'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('form_section', array(
            'label'   => __('Details'),
            'title'   => __('Details'),
            'content' => $this->getLayout()->createBlock('\MalibuCommerce\MConnect\Block\Adminhtml\Queue\Edit\Tabs\Form')->toHtml(),
        ));
        return parent::_beforeToHtml();
    }
}
