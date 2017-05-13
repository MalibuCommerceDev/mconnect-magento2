<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\System\Config;


class Lastsync
    extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue
    ) {
        $this->mConnectQueue = $mConnectQueue;

        parent::__construct($context);
    }
    /**
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->mConnectQueue->getLastSync($this->_code);
    }
}
