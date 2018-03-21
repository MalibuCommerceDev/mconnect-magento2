<?php

namespace MalibuCommerce\MConnect\Block\Navision;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class OrderHistory extends \Magento\Customer\Block\Account\Dashboard
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var string
     */
    protected $_template = 'navision/orders.phtml';

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order\History
     */
    protected $orderHistory;

    /**
     * Constructor
     *\MalibuCommerce\MConnect\Model\Queue $queue
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $customerAccountManagement
     * @param \MalibuCommerce\MConnect\Model\Queue
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Registry $registry,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $customerAccountManagement,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->registry = $registry;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerRepository = $customerRepository;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->queue = $queue;

        parent::__construct($context, $customerSession, $subscriberFactory, $customerRepository, $customerAccountManagement, $data);
    }

    /**
     * Retrieve form data
     *
     * @return array
     */
    protected function getFormData()
    {
        $data = $this->getData('mconnect-order-list');
        if ($data === null) {
            $formData = $this->customerSession->getCustomerFormData(true);
            $data = [];        $this->_logger->critical("entities: ".print_r($entities, 1));

            if ($formData) {
                $data['data'] = $formData;
                $data['customer_data'] = 1;
            }
            $this->setData('mconnect-order-list', $data);
        }
        return $data;
    }

    /**
     * Return request param
     *
     * @param $name
     * @return mixed
     */
    public function getFieldValue($name)
    {
        return $this->getRequest()->getParam($name, '');
    }

    /**
     * Return Orders
     *
     * @return bool|mixed
     */
    public function getOrders()
    {
        $entities = false;
        if ($this->getRequest()->getParam('search')) {
            try {
                $details = array(
                    'customer_number' => $this->customerSession->getCustomer()->getNavId(),
                );
                $params = array(
                    'date_from' => 'start_date',
                    'date_to' => 'end_date',
                    'po_number_from' => 'start_po_number',
                    'po_number_to' => 'end_po_number',
                    'order_number_from' => 'start_order_number',
                    'order_number_to' => 'end_order_number',
                );
                foreach ($params as $name => $key) {
                    $value = $this->getRequest()->getParam($name);
                    if ($value) {
                        $details[$key] = $value;
                    }
                }
                $this->queue->setData(array(
                    'code' => 'order_history',
                    'action' => 'list',
                    'details' => json_encode($details),
                ))
                    ->process();
                $entities = $this->registry->registry('MALIBUCOMMERCE_MCONNET_ORDER_HISTORY_ENTITIES');
            } catch (\Exception $e) {
                $this->_logger->critical($e->getMessage());
                return false;
            }
        }
        return $entities;
    }
}