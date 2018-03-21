<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Orderhistory extends \MalibuCommerce\MConnect\Model\Queue
{
    protected $_rootNode = 'open_order_list';
    protected $_listNode = 'order';

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order\History
     */
    protected $navOrderHistory;

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

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Order\History $navOrderHistory,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        array $data = []
    ) {
        $this->navOrderHistory = $navOrderHistory;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->registry = $registry;
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
        $this->queueFlagFactory = $queueFlagFactory;

        parent::__construct($context, $registry, $config, $scopeConfig, $queueFlagFactory, $data);
    }

    public function listAction()
    {
        $response = $this->navOrderHistory->import($this->getDetails());

        $entities = array();
        foreach ($response->{$this->_listNode} as $entity) {
            $data = array();
            foreach ($entity as $attr => $value) {
                $pieces = preg_split('/(?=[A-Z])/', $attr);
                foreach ($pieces as &$piece) {
                    $piece = strtolower($piece);
                }
                $data[implode('_', $pieces)] = (string)$value;
            }
            $dataObject = new \Magento\Framework\DataObject();
            $dataObject->setData($data);
            $entities[] = $dataObject;
        }
        $this->registry->register('MALIBUCOMMERCE_MCONNET_ORDER_HISTORY_ENTITIES', $entities);
        return;
    }
}