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
     * @var \Magento\Catalog\Api\ProductRepositoryInterface|ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $context;

    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $backendHelper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Productsync constructor.
     *
     * @param \Magento\Framework\Controller\Result\JsonFactory   $resultJsonFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \MalibuCommerce\MConnect\Model\Config              $config
     * @param \MalibuCommerce\MConnect\Model\QueueFactory        $queue
     * @param \Magento\Framework\Controller\ResultFactory        $result
     * @param \Magento\Catalog\Api\ProductRepositoryInterface    $productRepository
     * @param \MalibuCommerce\MConnect\Helper\Data               $helper
     * @param \Magento\Backend\Helper\Data                       $backendHelper
     * @param \Magento\Framework\App\Action\Context              $context
     */
    public function __construct(
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\QueueFactory $queue,
        \Magento\Framework\Controller\ResultFactory $result,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Framework\App\Action\Context $context
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queue = $queue;
        $this->resultFactory = $result;
        $this->productRepository = $productRepository;
        $this->helper = $helper;
        $this->backendHelper = $backendHelper;
        $this->context = $context;

        parent::__construct($context);
    }

    /**
     * Controller Execution
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->auth()) {

            $data['error'] = 1;
            $data['message'] = 'You are not authorized.';

            return $result->setData($data);
        }

        $productSku = $this->getRequest()->getParam('id');
        $data = array();
        if (!$this->scopeConfig->getValue('malibucommerce_mconnect/general/enabled')) {
            $data['error'] = 1;
            $data['message'] = 'M-Connect is disabled).';
        } else {
            try {
                $queue = $this->queue->create()->add(
                    'product',
                    'import_single',
                    null,
                    array('nav_id' => $productSku)
                );

                $queue->process();

                $message = $queue->getMessages();
                $queueStatus = $queue->getStatus();
                if ($queueStatus === \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS) {
                    $product = $this->productRepository->get($productSku, true, null, true);

                    $productEditUrl = $this->backendHelper->getUrl('catalog/product/edit', [
                        'id' => $product->getId()
                    ]);
                    $data['success'] = 1;
                    $data['message'] = $message;
                    $data['url'] = $productEditUrl;
                } else {
                    $data['error'] = 1;
                    $data['message'] = $message;
                    $data['detail'] = $this->getLogHtml($queue->getId());
                }
            } catch (\Exception $e) {
                $data['error']   = 1;
                $data['message'] = $e->getMessage();
            }
        }

        return $result->setData($data);
    }

    /**
     * Check url for authorized action
     *
     * @return bool
     */
    protected function auth()
    {
        $auth = trim($this->getRequest()->getParam('auth'));
        $password = $this->config->getTriggerPassword();
        $triggerPassword = md5($password);
        if (!$auth || $auth != $triggerPassword) {
            return false;
        }
        return true;
    }

    /**
     * Return queue log in html
     *
     * @param $queueId
     * @return string
     */
    public function getLogHtml($queueId)
    {
        $content = $this->helper->getLogFileContents($queueId, 1);
        if (!$content) {
            return '';
        }
        return '<div class="malibucommerce-mconnect-parsed">' . $content . '</div>';
    }
}
