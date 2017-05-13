<?php
namespace MalibuCommerce\MConnect\Controller;


class Sync extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    protected $backendUrlInterface;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue,
        \Magento\Backend\Model\UrlInterface $backendUrlInterface
    ) {
        $this->registry = $registry;
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectQueue = $mConnectQueue;
        $this->backendUrlInterface = $backendUrlInterface;
        parent::__construct(
            $context
        );
    }

    protected function _initLayout()
    {
        $this->loadLayout();
        $id = $this->getRequest()->getParam('id');
        $this->registry->register('mconnect_sync_identifier', $id);
        $this->getLayout()->getBlock('root')
            ->setAuth($this->getRequest()->getParam('auth'))
            ->setIdentifier($id)
        ;
        return $this;
    }

    protected function _initSync()
    {
        $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')->setCurrentStore(0);
        return $this;
    }

    public function preDispatch()
    {
        parent::preDispatch();
        $auth = trim($this->getRequest()->getParam('auth'));
        if (!$auth || $auth != md5($this->mConnectConfig->get('navision/trigger_password'))) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden')->sendHeadersAndExit();
        }
    }

    public function productAction()
    {
        $this
            ->_initLayout()
            ->renderLayout()
        ;
    }

    public function productsyncAction()
    {
        $this->_initSync();
        $data = array();
        try {
            $queue = $this->mConnectQueue->add('product', 'import_single', null, array('nav_id' => $this->getRequest()->getParam('id')))->process();
            $message = $queue->getMessage();
            if ($queue->getStatus() === MalibuCommerce_Mconnect_Model_Queue::STATUS_SUCCESS) {
                $data['success'] = 1;
                $data['message'] = $message;
                $data['url'] = $this->backendUrlInterface->getUrl('adminhtml/catalog_product/edit', array('id' => $queue->getEntityId()));
            } else {
                $data['error'] = 1;
                $data['message'] = $message;
            }
        } catch (Exception $e) {
            $data['error'] = 1;
            $data['message'] = $e->getMessage();
        }
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($data));
    }

    public function execute()
    {
        parent::execute();
    }
}
