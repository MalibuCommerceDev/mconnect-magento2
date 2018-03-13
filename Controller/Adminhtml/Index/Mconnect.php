<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Index;

class Mconnect extends \Magento\Customer\Controller\Adminhtml\Index
{
    /**
     * Customer MConnect tab
     *
     * @return \Magento\Framework\View\Result\Layout
     */
    public function execute()
    {

        $this->initCurrentCustomer();
        $resultLayout = $this->resultLayoutFactory->create();
        return $resultLayout;
    }


}