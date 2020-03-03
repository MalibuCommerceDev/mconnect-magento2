<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use MalibuCommerce\MConnect\Model\Pricerule;
use MalibuCommerce\MConnect\Model\ResourceModel\Adminhtml\Pricerule\Grid\CollectionFactory;

class MassDelete extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceruleAction
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Pricerule
     */
    protected $priceRuleModel;

    /**
     * MassDelete constructor.
     *
     * @param Context                                    $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Registry                $coreRegistry
     * @param Filter                                     $filter
     * @param CollectionFactory                          $collectionFactory
     * @param Pricerule                                  $priceRuleModel
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $coreRegistry,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Pricerule $priceRuleModel
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->priceRuleModel = $priceRuleModel;
        parent::__construct($context, $resultPageFactory, $coreRegistry);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        /** @var \MalibuCommerce\MConnect\Model\Pricerule $model */
        foreach ($collection as $item) {
            $priceRuleModel = $this->priceRuleModel->load($item->getId());
            $priceRuleModel->delete();
        }

        $this->messageManager->addSuccess(__('A total of %1 record(s) have been deleted.', $collectionSize));

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
