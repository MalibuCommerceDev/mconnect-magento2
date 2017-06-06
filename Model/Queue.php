<?php
namespace MalibuCommerce\MConnect\Model;


class Queue extends \Magento\Framework\Model\AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';

    const REGISTRY_PREFIX = 'MALIBUECOMMERCE_MCONNECT_NAV_';

    protected $_attributes = array('id', 'url', 'username', 'password');

    protected $_eventPrefix = 'malibucommerce_mconnect_queue';

    protected $_eventObject = 'queue';

    protected $_captureEntityId = false;

    protected $_messages = '';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Connection\Collection
     */
    protected $mConnectResourceConnectionCollection;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $customerCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Lastsync
     */
    protected $mConnectLastsync;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Resource\Connection\Collection $mConnectResourceConnectionCollection,
        \Magento\Customer\Model\Customer $customerCustomer,
        \MalibuCommerce\MConnect\Model\Lastsync $mConnectLastsync,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \MalibuCommerce\MConnect\Model\Queue\Customer $queueCustomer,
        \MalibuCommerce\MConnect\Model\Queue\Product $queueProduct,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->mConnectResourceConnectionCollection = $mConnectResourceConnectionCollection;
        $this->customerCustomer = $customerCustomer;
        $this->mConnectLastsync = $mConnectLastsync;
        $this->mConnectConfig = $mConnectConfig;
        $this->mConnectQueueCustomer = $queueCustomer;
        $this->mConnectQueueProduct = $queueProduct;
        $this->scopeConfig = $scopeConfig;

        parent::__construct(
            $context,
            $registry
        );
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
            $this
                ->setCode($code)
                ->setAction($action)
                ->setEntityId($id)
                ->setDetails($details) 
                ->setCreatedAt(date('Y-m-d H:i:s'))
                ->setStatus(self::STATUS_PENDING)
                ->save()
            ;
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
        $this
            ->setStatus(self::STATUS_RUNNING)
            ->setStartedAt(date('Y-m-d H:i:s'))
            ->save()
        ;

        $code        = $this->getCode();
        /*$class       = '"\MalibuCommerce\MConnect\Model\Queue\"' . ucfirst($code);
        $classExists = false;
        try {
            $classExists = class_exists($class);
        } catch (Exception $e) {
            // do nothing
        }
        if (!$classExists) {
            $this->_endProcess(self::STATUS_ERROR, sprintf('M-Connect error: The code "%s" is not recognized and cannot be processed.  You may need to update the M-Connect module or clear the Magento cache.', $code));
            return $this;
        }*/
        $modelName = 'mConnectQueue' . ucfirst($code);
        $model = $this->$modelName;
        $action = $this->getAction();
        $prefix = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $action))));
        $method = $prefix . 'Action';
        if (!method_exists($model, $method)) {
            $this->_endProcess(self::STATUS_ERROR, sprintf('M-Connect error: The action "%s" is not recognized and cannot be processed.  You may need to update the M-Connect module or clear the Magento cache.', $action));
            return $this;
        }
        $this->registry->register('MALIBUCOMMERCE_MCONNET_ACTIVE_QUEUE', $this->getId());
        try {
            $this->_initConnection();
            if (($code == 'customer') && ($action == 'export')) {
                $model->{$method}($this->getEntityId());
            } else {
                $model->{$method}();
            }

            if ($model->_captureEntityId) {
                $this->setEntityId($model->getEntityId());
            }
            $this->_endProcess(self::STATUS_SUCCESS, $model->getMessages());
        } catch (Exception $e) {
            $this->_logger->critical($e);
            $this->_endProcess(self::STATUS_ERROR, $e->getMessage());
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

    protected function _endProcess($status, $message = null)
    {
        $this
            ->setMessage($message)
            ->setFinishedAt(date('Y-m-d H:i:s'))
            ->setStatus($status)
            ->setConnectionId($this->getConfig()->getNavConnectionId())
            ->save()
        ;
        return $this;
    }

    protected function _initConnection()
    {
        $connection = $this->_getConnection();
        foreach ($this->_attributes as $attr) {
            if ($this->registry->registry(self::REGISTRY_PREFIX . strtoupper($attr))) {
                $this->registry->unregister(self::REGISTRY_PREFIX . strtoupper($attr));
            }
            $this->registry->register(self::REGISTRY_PREFIX . strtoupper($attr), $connection->getData($attr));
        }
        return $this;
    }

    protected function _getConnection()
    {
        $connection = new \Magento\Framework\DataObject();

        $connection->setId('1');
        $connection->setUrl($this->getConfig()->get('nav_connection/url'));

        $connection->setUsername($this->getConfig()->get('nav_connection/username'));

        $connection->setPassword($this->getConfig()->get('nav_connection/password'));

        return $connection;

        /*$connections = $this->mConnectResourceConnectionCollection;
        $connections->getSelect()->order('sort_order');
        foreach ($connections as $connection) {
            $rules = trim($connection->getRules());
            if (empty($rules)) {
                return $connection;
            }
            $rules = json_decode($rules, true);
            if ($rules === null) {
                continue;
            }
            foreach (array_keys($rules) as $key) {
                switch ($key) {
                    case 'code':
                    case 'action':
                    case 'entity_id':
                        if ($rules[$key] != $this->getData($key)) {
                            continue 3;
                        }
                        break;
                    case 'website_id':
                        switch ($this->getCode()) {
                            case 'customer':
                                if ($this->getEntityId()) {
                                    $entity = $this->customerCustomer->load($this->getEntityId());
                                    if ($entity->getWebsiteId() != $rules['website_id']) {
                                        continue;
                                    }
                                }
                                break;
                        }
                        break;
                }
            }
            return $connection;
        }
        throw new \Exception('No connection configured for this action.');*/
    }

    public function setLastSync($name, $time)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('\MalibuCommerce\MConnect\Model\Lastsync');

        $sync = $model->getCollection()
            ->addFieldToFilter('name', $name)
            ->getFirstItem();
        if (!$sync || !$sync->getId()) {
            $sync = $model;
        }
        $sync
            ->setName($name)
            ->setTime($time)
            ->save()
        ;
    }

    public function getLastSync($name)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $model = $objectManager->create('\MalibuCommerce\MConnect\Model\Lastsync');

        $sync = $model->getCollection()
            ->addFieldToFilter('name', $name)
            ->getFirstItem();
        if (!$sync || !$sync->getId()) {
            return false;
        }
        return date('Y-m-d\TH:i:s', strtotime($sync->getTime()));
    }

    public function getConfig()
    {
        return $this->mConnectConfig;
    }

    public function getMessages()
    {
        return $this->_messages;
    }
}
