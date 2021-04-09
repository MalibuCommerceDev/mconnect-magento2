<?php

namespace MalibuCommerce\MConnect\Block\Navision;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Block\Account\Dashboard;
use Magento\Customer\Model\Session;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template\Context;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * @method setOrders(array $orders) OrderHistory
 * @method getOrders() array
 */
class OrderHistory extends Dashboard
{
    /**
     * @var string
     */
    protected $_template = 'navision/orders.phtml';

    /**
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * OrderHistory block constructor
     *
     * @param PriceHelper                 $priceHelper
     * @param Context                     $context
     * @param Session                     $customerSession
     * @param SubscriberFactory           $subscriberFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface  $customerAccountManagement
     * @param array                       $data
     */
    public function __construct(
        PriceHelper $priceHelper,
        Context $context,
        Session $customerSession,
        SubscriberFactory $subscriberFactory,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $customerAccountManagement,
        array $data = []
    ) {
        $this->priceHelper = $priceHelper;

        $this->customerSession = $customerSession;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerRepository = $customerRepository;
        $this->customerAccountManagement = $customerAccountManagement;
        parent::__construct(
            $context,
            $this->customerSession,
            $this->subscriberFactory,
            $this->customerRepository,
            $this->customerAccountManagement,
            $data
        );
    }

    /**
     * Return request param
     *
     * @param $name
     *
     * @return mixed
     */
    public function getFieldValue($name)
    {
        return $this->getRequest()->getParam($name, '');
    }

    /**
     * @return bool
     */
    public function isSearchInitiated()
    {
        $params = $this->getRequest()->getParams();

        return !empty($params);
    }

    /**
     * @return PriceHelper
     */
    public function getPriceHelper()
    {
        return $this->priceHelper;
    }
}
