<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\System\Config\Version;

class Module extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        $this->_moduleList = $moduleList;

        parent::__construct($context);
    }

    /**
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $moduleCode = 'MalibuCommerce_MConnect';
        $moduleInfo = $this->_moduleList->getOne($moduleCode);
        return $moduleInfo['setup_version'];
    }
}
