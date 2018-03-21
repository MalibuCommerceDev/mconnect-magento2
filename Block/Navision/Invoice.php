<?php

namespace MalibuCommerce\MConnect\Block\Navision;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class Invoice extends \Magento\Customer\Block\Account\Dashboard
{
    /**
     * @var string
     */
    protected $_template = 'navision/invoice.phtml';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

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
     *
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
     * Return invoices
     *
     * @return bool|mixed
     */
    public function getInvoices()
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
                    'invoice_number_from' => 'start_invoice_number',
                    'invoice_number_to' => 'end_invoice_number',
                );
                foreach ($params as $name => $key) {
                    $value = $this->getRequest()->getParam($name);
                    if ($value) {
                        $details[$key] = $value;
                    }
                }
                $this->queue->setData(array(
                    'code' => 'sales_invoice',
                    'action' => 'list',
                    'details' => json_encode($details),
                ))
                    ->process();
                $entities = $this->registry->registry('MALIBUCOMMERCE_MCONNET_INVOICES');
            } catch (\Exception $e) {
                $this->_logger->critical($e->getMessage());
                return false;
            }
        }
        return $entities;

    }
}