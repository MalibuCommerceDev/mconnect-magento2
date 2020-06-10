<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Queue;

use Magento\Backend\App\Action;

abstract class Queue extends Action
{
    const ADMIN_RESOURCE = 'MalibuCommerce_MConnect::queue';

    /**
     * @var bool|\Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    /**
     * @var \MalibuCommerce\MConnect\Model\Cron\Queue
     */
    protected $mConnectCronQueue;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue,
        \MalibuCommerce\MConnect\Model\Cron\Queue $mConnectCronQueue,
        \MalibuCommerce\MConnect\Helper\Data $helper
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->mConnectQueue = $mConnectQueue;
        $this->mConnectCronQueue = $mConnectCronQueue;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->messageManager = $context->getMessageManager();
        $this->helper = $helper;

        parent::__construct($context);
    }
}
