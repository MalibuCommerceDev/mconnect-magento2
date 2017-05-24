<?php
namespace MalibuCommerce\MConnect\Controller\Adminhtml\MalibuCommerce\MConnect;


class ConnectionController
    extends \Magento\Backend\App\Action
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Connection
     */
    protected $mConnectConnection;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $backendSession;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $backendAuthSession;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Connection $mConnectConnection,
        \Magento\Framework\Registry $registry,
        \Magento\Backend\Model\Session $backendSession,
        \Magento\Backend\Model\Auth\Session $backendAuthSession
    ) {
        $this->mConnectConnection = $mConnectConnection;
        $this->registry = $registry;
        $this->backendSession = $backendSession;
        $this->backendAuthSession = $backendAuthSession;
    }
    protected function _initEntity($idFieldName = 'id')
    {
        $id = (int) $this->getRequest()->getParam($idFieldName);
        $entity = $this->mConnectConnection;
        if ($id) {
            $entity->load($id);
        }
        $this->registry->register('current_entity', $entity);
        return $this;
    }

    public function indexAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('system')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('M-Connect'), $this->__('M-Connect'))
            ->_addBreadcrumb($this->__('Connection Manager'), $this->__('Connection Manager'))
            ->_title($this->__('System'))
            ->_title($this->__('M-Connect'))
            ->_title($this->__('Connection Manager'))
            ->renderLayout();
    }

    /**
     * Customer edit action
     */
    public function editAction()
    {
        $this->_initEntity();
        $entity = $this->registry->registry('current_entity');
        $title = $entity->getId() ? $entity->getName() : $this->__('New Connection');
        $this->loadLayout()
            ->_setActiveMenu('system')
            ->_addBreadcrumb($this->__('System'), $this->__('System'))
            ->_addBreadcrumb($this->__('M-Connect'), $this->__('M-Connect'))
            ->_addBreadcrumb($title, $title)
            ->_title($this->__('System'))
            ->_title($this->__('M-Connect'))
            ->_title($title);
        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('malibucommerce_mconnect/adminhtml_connection_edit', 'connection')
                ->setEditMode((bool) $entity->getId())
        );
        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function saveAction()
    {
        $model = $this->mConnectConnection;
        $post = $this->getRequest()->getPost();
        if ($post) {
            if (isset($post['id']) && $post['id']) {
                $model->load($post['id']);
            }
            try {
                $model->setData($post);
                $model->save();
                $redirectBack = $this->getRequest()->getParam('back', false);
                $this->backendSession->addSuccess($this->__('The connection has been saved.'));
                if ($redirectBack !== 'edit') {
                    return $this->getResponse()->setRedirect($this->getUrl('*/*'));
                }
            } catch (Exception $e) {
                $this->backendSession->addError($e->getMessage());
            }
        }
        $id = $model->getId();
        if ($id) {
            $this->getResponse()->setRedirect($this->getUrl('*/*/edit', array('id' => $id)));
        } else {
            $this->getResponse()->setRedirect($this->getUrl('*/*/new'));
        }
    }

    public function deleteAction()
    {
        $this->_initEntity();
        $model = $this->registry->registry('current_entity');
        if ($model->getId()) {
            try {
                $model->delete();
                $this->backendSession->addSuccess($this->__('The connection has been removed.'));
            } catch (Exception $e) {
                $this->backendSession->addError($e->getMessage());
            }
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*'));
    }

    protected function _isAllowed()
    {
        return $this->backendAuthSession->isAllowed('system/malibucommerce_mconnect/connection');
    }

    public function execute()
    {
    }
}
