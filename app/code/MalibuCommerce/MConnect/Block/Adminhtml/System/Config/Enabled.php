<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\System\Config;


class Enabled
    extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig
    ) {
        $this->mConnectConfig = $mConnectConfig;

        parent::__construct($context);
    }
    /**
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->mConnectConfig->isModuleEnabled()) {
            $imgUrl = $this->getSkinUrl('images/success_msg_icon.gif');
        } else {
            $imgUrl = $this->getSkinUrl('images/error_msg_icon.gif');
        }
        return <<<HTML
<img src="{$imgUrl}"></img>
HTML;
    }
}
