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
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig
    )
    {
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

        $password = $this->mConnectConfig->getTriggerPassword();
        $this->getResponse()->setHeader('Content-type', 'application/json');
        if ($password) {
            $success = true;
        }
        return $result->setData(['success' => $success, 'p' => $password]);
    }
}