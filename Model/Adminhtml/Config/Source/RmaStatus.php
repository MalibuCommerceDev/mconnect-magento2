<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Source;

use MalibuCommerce\MConnect\Model\Queue;

class RmaStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->moduleManager->isEnabled('Magento_Rma')) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $rmaStatus = $objectManager->create('Magento\Rma\Model\Rma\Source\Status');
            return $rmaStatus->getAllOptions();
        } else {
            return [
                ['value' => '', 'label' => __('RMA module disabled')],
            ];
        }
        
    }
}