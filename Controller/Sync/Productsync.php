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

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Magento\Framework\Controller\ResultFactory $result,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queue = $queue;
        $this->resultFactory = $result;
        $this->resultPageFactory = $resultPageFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        if (!$this->_auth()) {
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            //$resultRedirect->setUrl($this->_redirect->getRefererUrl());
            $resultRedirect->setPath('/');
            return $resultRedirect;
        }

        // http://mconnect2.malibucommerce.com/mconnect/sync/product/id/SPK-100/auth/4b2667b4f66bceb633d09e014dafe6b0
        $productSku = $this->getRequest()->getParam('id');
        //$auth = $this->getRequest()->getParam('auth');
        //$this->_initSync();
        $data = array();
        try {
            //$queue = Mage::getModel('malibucommerce_mconnect/queue')
            //->add('product', 'import_single', null, array(
            //  'nav_id' => $this->getRequest()->getParam('id'))
            //)->process();
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
//                echo "cl: ". get_class($this->queue);
//                aa();

                $message = $queue->getMessages();
//                echo "<pre>". print_r($queue->getData(), 1) ."</pre>";
//                aa();
                $queueStatus = $queue->getStatus();
                if ($queueStatus === \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS) {
                    $data['success'] = 1;
                    $data['message'] = $message;
//                    $resultRedirect->getUrl()

//                    $data['url'] = Mage::getModel('adminhtml/url')->getUrl('adminhtml/catalog_product/edit', array('id' => $queue->getEntityId()));
                } else {
                    $data['error'] = 1;
                    $data['message'] = $message;
                    $data['detail'] = $this->getLogHtml($this->queue);
                }
            }
        } catch (\Exception $e) {
            //Mage::logException($e);
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
        //$triggerPassword = $this->config->get('malibucommerce_mconnect/nav_connection/trigger_password');
        $triggerPassword = md5($password);
        if (!$auth || $auth != $triggerPassword) {
            return false;
        }
        return true;
    }

    protected function _initSync()
    {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        return $this;
    }

    public function getLogHtml($row)
    {
        $content = Mage::helper('malibucommerce_mconnect/log')->toHtml($row->getId());
        if (!$content) {
            return '';
        }
        return '<div class="malibucommerce-mconnect-parsed">' . $content . '</div>';
    }
}