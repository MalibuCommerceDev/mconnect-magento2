<?php
namespace MalibuCommerce\MConnect\Controller\Adminhtml\MalibuCommerce\MConnect;


class QueueController
    extends \Magento\Backend\App\Action
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $backendSession;

    /**
     * @var \MalibuCommerce\MConnect\Model\Cron\Queue
     */
    protected $mConnectCronQueue;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $backendAuthSession;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue,
        \Magento\Backend\Model\Session $backendSession,
        \MalibuCommerce\MConnect\Model\Cron\Queue $mConnectCronQueue,
        \Magento\Backend\Model\Auth\Session $backendAuthSession
    ) {
        $this->mConnectQueue = $mConnectQueue;
        $this->backendSession = $backendSession;
        $this->mConnectCronQueue = $mConnectCronQueue;
        $this->backendAuthSession = $backendAuthSession;
    }
    public function indexAction()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            $this->_forward('grid');
            return;
        }
        $this->loadLayout()
            ->_setActiveMenu('system')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('M-Connect'), $this->__('M-Connect'))
            ->_addBreadcrumb($this->__('Synchronization Queue'), $this->__('Synchronization Queue'))
            ->_title($this->__('System'))
            ->_title($this->__('M-Connect'))
            ->_title($this->__('Synchronization Queue'))
            ->renderLayout();
    }

    public function newAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('system')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('M-Connect'), $this->__('M-Connect'))
            ->_addBreadcrumb($this->__('Synchronization Queue'), $this->__('Synchronization Queue'))
            ->_addBreadcrumb($this->__('Add Item'), $this->__('Add Item'))
            ->_title($this->__('System'))
            ->_title($this->__('M-Connect'))
            ->_title($this->__('Synchronization Queue'))
            ->_title($this->__('Add Item'))
            ->_addContent($this->getLayout()->createBlock('malibucommerce_mconnect/adminhtml_queue_edit'))
            ->_addLeft($this->getLayout()->createBlock('malibucommerce_mconnect/adminhtml_queue_edit_tabs'))
            ->renderLayout();
    }

    public function saveAction()
    {
        $postData = $this->getRequest()->getPost();
        if ($postData) {
            try {
                $this->mConnectQueue
                    ->add($postData['code'], $postData['action'], $postData['entity_id'])
                ;
                $this->backendSession->addSuccess(
                    __('Item added to queue')
                );
                $this->backendSession->setQueueData(false);
                $this->_redirect('*/*/');
            } catch (Exception $e) {
                $this->backendSession->addError($e->getMessage());
                $this->backendSession->setQueueData($postData);
                $this->_redirect('*/*/new', array('id' => $this->getRequest()->getParam('id')));
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function syncAction()
    {
        $result = $this->mConnectCronQueue->process();
        $this->backendSession->addSuccess(
            __($result)
        );
        $this->_redirect('*/*/');
    }

    public function logAction()
    {
        $id = $this->getRequest()->getParam('id');
        $this->getResponse()->setHeader('Content-type', 'text/plain');
        if (!$id) {
            return;
        }
        $file = \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client::getLogFile($id);
        if (!file_exists($file)) {
            $body = 'Log file not found.';
        } else {
            $body = file_get_contents($file);
        }
        $this->getResponse()->setBody(html_entity_decode($body));
    }

    public function massQueueAction()
    {
        try {
            $ids = $this->getRequest()->getPost('ids', array());
            foreach ($ids as $id) {
                $this->mConnectQueue->load($id)->reQueue();
            }
            $this->backendSession
                ->addSuccess(__('Item(s) have been re-queued.'));
        } catch (Exception $e) {
            $this->backendSession->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    public function massQueueAndSyncAction()
    {
        $queueIds = [];
        try {
            $ids = $this->getRequest()->getPost('ids', array());
            foreach ($ids as $id) {
                $queue = $this->mConnectQueue->load($id)->reQueue();
                if ($queue->getId()) {
                    $queueIds[] = $queue->getId();
                }
            }
            $this->backendSession
                ->addSuccess(__('Item(s) have been re-queued.'));
        } catch (Exception $e) {
            $this->backendSession->addError($e->getMessage());
        }
        try {
            foreach ($queueIds as $id) {
                $this->mConnectQueue->load($id)->process();
            }
            $this->backendSession
                ->addSuccess(__('Item(s) have been synced.'));
        } catch (Exception $e) {
            $this->backendSession->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    public function massSyncAction()
    {
        try {
            $ids = $this->getRequest()->getPost('ids', array());
            foreach ($ids as $id) {
                $this->mConnectQueue->load($id)->process();
            }
            $this->backendSession
                ->addSuccess(__('Item(s) have been synced.'));
        } catch (Exception $e) {
            $this->backendSession->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    protected function _isAllowed()
    {
        return $this->backendAuthSession->isAllowed('system/malibucommerce_mconnect/queue');
    }

    public function execute()
    {
    }
}
