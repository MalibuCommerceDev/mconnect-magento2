<?php

namespace MalibuCommerce\MConnect\Controller\Sync;

class Product extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * Product constructor.
     *
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->pageFactory = $pageFactory;

        parent::__construct($context);
    }

    /**
     * Controller Execution
     */
    public function execute()
    {
        return $this->pageFactory->create();
    }
}