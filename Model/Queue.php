<?php
namespace MalibuCommerce\MConnect\Model;


class Queue extends \Magento\Framework\Model\AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';

    const REGISTRY_PREFIX = 'MALIBUECOMMERCE_MCONNECT_NAV_';

    protected $attributes = array('id', 'url', 'username', 'password');

    protected $_eventPrefix = 'malibucommerce_mconnect_queue';

    protected $_eventObject = 'queue';

    protected $captureEntityId = false;

    protected $messages = '';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /** @var \MalibuCommerce\MConnect\Model\Queue\Customer  */
    protected $mConnectQueueCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Product
     */
    protected $mConnectQueueProduct;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Order
     */
    protected $mConnectQueueOrder;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\Invoice
     */
    protected $mConnectQueueInvoice;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Queue\Customer $queueCustomer,
        \MalibuCommerce\MConnect\Model\Queue\Product $queueProduct,
        \MalibuCommerce\MConnect\Model\Queue\Order $queueOrder,
        \MalibuCommerce\MConnect\Model\Queue\Invoice $queueInvoice,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->config = $config;
        $this->mConnectQueueCustomer = $queueCustomer;
        $this->mConnectQueueProduct = $queueProduct;
        $this->mConnectQueueOrder = $queueOrder;
        $this->mConnectQueueInvoice = $queueInvoice;
        $this->scopeConfig = $scopeConfig;
        $this->queueFlagFactory = $queueFlagFactory;

        parent::__construct($context, $registry);
    }


    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Resource\Queue');
    }

    public function add($code, $action, $id = null, $details = array())
    {
        if (!$this->getConfig()->getFlag('general/enabled')) {
            return $this;
        }
        $id      = $id ? $id : null;
        $details = is_array($details) ? (count($details) ? json_encode($details) : null) : $details;
        $count   = $this->getCollection()->findMatchingPending($code, $action, $id, $details)->getSize();
        if (!$count) {
            $this->setCode($code)
                ->setAction($action)
                ->setEntityId($id)
                ->setDetails($details) 
                ->setCreatedAt(date('Y-m-d H:i:s'))
                ->setStatus(self::STATUS_PENDING)
                ->save();
        }

        return $this;
    }

    public function reQueue()
    {
        return $this->add(
            $this->getCode(),
            $this->getAction(),
            $this->getEntityId(),
            $this->getDetails()
        );
    }

    public function process()
    {
        if (!$this->getConfig()->getFlag('general/enabled')) {
            return false;
        }

        $this->setStatus(self::STATUS_RUNNING)
            ->setStartedAt(date('Y-m-d H:i:s'))
            ->save();

        $code = $this->getCode();
        $modelName = 'mConnectQueue' . ucfirst($code);
        $model = $this->$modelName;
        $action = $this->getAction();
        $prefix = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $action))));
        $method = $prefix . 'Action';
        if (!method_exists($model, $method)) {
            $this->endProcess(self::STATUS_ERROR, sprintf('M-Connect error: The action "%s" is not recognized and cannot be processed.  You may need to update the M-Connect module or clear the Magento cache.', $action));
            return $this;
        }
        $this->registry->register('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE', $this->getId());

        try {
            $this->initConnection();
            if (($code == 'customer' || $code == 'order') && ($action == 'export')) {
                $model->{$method}($this->getEntityId());
            } else {
                $model->{$method}();
            }

            if ($model->captureEntityId) {
                $this->setEntityId($model->getEntityId());
            }
            $this->endProcess(self::STATUS_SUCCESS, $model->getMessages());
        } catch (\Exception $e) {
            $this->_logger->critical($e);
            $this->endProcess(self::STATUS_ERROR, $e->getMessage());
        }
        $this->registry->unregister('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE', $this->getId());

        return $this;
    }

    public function getAllStatuses()
    {
        $reflect = new \ReflectionClass(get_class($this));
        $constants = $reflect->getConstants();
        $statuses = array();
        foreach ($constants as $key => $value) {
            if (strpos($key, 'STATUS_') === 0) {
                $statuses[$value] = $value;
            }
        }
        return $statuses;
    }

    protected function endProcess($status, $message = null)
    {
        $this->setMessage($message)
            ->setFinishedAt(date('Y-m-d H:i:s'))
            ->setStatus($status)
            ->setConnectionId($this->getConfig()->getNavConnectionId())
            ->save();

        return $this;
    }

    protected function initConnection()
    {
        $connection = $this->getConnection();

        foreach ($this->attributes as $attr) {
            if ($this->registry->registry(self::REGISTRY_PREFIX . strtoupper($attr))) {
                $this->registry->unregister(self::REGISTRY_PREFIX . strtoupper($attr));
            }
            $this->registry->register(self::REGISTRY_PREFIX . strtoupper($attr), $connection->getData($attr));
        }

        return $this;
    }

    protected function getConnection()
    {
        $connection = new \Magento\Framework\DataObject();

        $connection->setId('1');
        $connection->setUrl($this->getConfig()->get('nav_connection/url'));
        $connection->setUsername($this->getConfig()->get('nav_connection/username'));
        $connection->setPassword($this->getConfig()->get('nav_connection/password'));

        return $connection;
    }

    public function getLastSyncTime($code)
    {
        $flag = $this->queueFlagFactory->create()->setQueueFlagCode($code)->loadSelf();
        $time = $flag->hasData() ? $flag->getLastUpdate() : '';
        if (!$time) {
            return false;
        }

        return date('Y-m-d\TH:i:s', strtotime($time));
    }

    public function setLastSyncTime($code, $time)
    {
        $this->queueFlagFactory->create()->setQueueFlagCode($code)->loadSelf()
            ->setLastUpdate($time)
            ->save();

        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function hasRecords($result)
    {
        if (isset($result->status->end_of_records) && (string) $result->status->end_of_records === 'true') {
            return false;
        }
        if ((int) $result->status->record_count <= 0) {
            return false;
        }
        return true;
    }
}
