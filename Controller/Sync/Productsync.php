<?php

namespace MalibuCommerce\MConnect\Controller\Sync;

use Magento\Framework\App\Action\Action;

class Productsync extends Action
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;


    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $context;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Magento\Framework\Controller\ResultFactory $result,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queue = $queue;
        $this->resultFactory = $result;
        $this->_storeManager = $storeManager;
        $this->context = $context;

        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->_auth()) {
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('/');
            return $resultRedirect;
        }

        $productSku = $this->getRequest()->getParam('id');
        $this->_initSync();
        $data = array();
        try {
            if (!$this->scopeConfig->getValue('malibucommerce_mconnect/general/enabled')) {
                $data['error'] = 1;
                $data['message'] = 'M-Connect is disabled).';
            } else {
                $queue = $this->queue->create()->add(
                    'product',
                    'import',
                    $productSku
                );
                $queue->process();

                $message = $queue->getMessages();
                $queueStatus = $queue->getStatus();
                if ($queueStatus === \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS) {
                    $data['success'] = 1;
                    $data['message'] = $message;
                    $url = $this->context->getUrl();
                    $productEditUrl = $url->getUrl('admin/catalog/product/edit', array('id' => $queue->getEntityId()));
                    $data['url'] = $productEditUrl;
                } else {
                    $data['error'] = 1;
                    $data['message'] = $message;
                    $data['detail'] = $this->getLogHtml($this->queue);
                }
            }
        } catch (\Exception $e) {
            $data['error']   = 1;
            $data['message'] = $e->getMessage();
            $data['detail']  = $this->getLogHtml($queue);
        }
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($data));
    }

    protected function _auth()
    {
        $auth = trim($this->getRequest()->getParam('auth'));
        $password = $this->config->getTriggerPassword();
        $triggerPassword = md5($password);
        if (!$auth || $auth != $triggerPassword) {
            return false;
        }
        return true;
    }

    protected function _initSync()
    {
        $this->_storeManager->setCurrentStore(\Magento\Store\Model\Store::ADMIN_CODE);
        return $this;
    }

//    public function getLogHtml($row)
//    {
//        $content = Mage::helper('malibucommerce_mconnect/log')->toHtml($row->getId());
//        if (!$content) {
//            return '';
//        }
//        return '<div class="malibucommerce-mconnect-parsed">' . $content . '</div>';
//    }
}