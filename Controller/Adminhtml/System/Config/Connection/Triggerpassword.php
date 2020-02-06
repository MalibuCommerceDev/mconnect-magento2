<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\System\Config\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Triggerpassword extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->mConnectConfig = $mConnectConfig;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $success = false;

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        $websiteId = $this->getRequest()->getParam('website', 0);
        $storeId = $this->getRequest()->getParam('store', 0);
        if ($storeId) {
            $websiteId = $this->storeManager->getStore($storeId)->getId();
        }
        if (empty($websiteId)) {
            $websiteId = null;
        }
        $password = $this->mConnectConfig->getTriggerPassword($websiteId);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        if ($password) {
            $success = true;
        }
        return $result->setData(['success' => $success, 'p' => $password]);
    }
}
