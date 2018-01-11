<?php

namespace MalibuCommerce\MConnect\Model\Resource\Pricerule;

use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\Session;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface
     */
    protected $customer;

    /**
     * @var \Magento\Customer\Model\CustomerRegistry
     */
    protected $customerRegistry;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface    $entityFactory
     * @param \Psr\Log\LoggerInterface                                     $logger
     * @param \Magento\Customer\Model\CustomerRegistry                     $CustomerRegistry
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager
     * @param \Magento\Framework\DB\Adapter\AdapterInterface               $connection
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb         $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        Session $customerSession,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->customerRegistry = $customerRegistry;
        $this->customerSession = $customerSession;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Pricerule', 'MalibuCommerce\MConnect\Model\Resource\Pricerule');
    }

    public function applyAllFilters($sku, $qty, $navisionCustomerId = false, $customerPriceGroup = null, $dateStart = null, $dateEnd = null)
    {
        $this->applySkuFilter($sku)
            ->applyQtyFilter($qty)
            ->applyNavisionCustomerIdFilter($navisionCustomerId)
            ->applyCustomerPriceGroup($customerPriceGroup)
            ->applyDateStartFilter($dateStart)
            ->applyDateEndFilter($dateEnd);
        $this->getSelect()->order('main_table.price ASC');

        return $this;
    }

    public function applySkuFilter($value)
    {
        return $this->applyNullableFilter('sku', $value);
    }

    public function applyQtyFilter($value)
    {
        if ($value !== null) {
            $this->addFieldToFilter('qty_min', array(
                array('to' => $value),
                array('null' => true),
            ));
        }

        return $this;
    }

    public function applyNavisionCustomerIdFilter($value = false)
    {
        if (!$value && $this->getCustomer()) {
            $value = $this->getCustomer()->getNavId();
        }

        return $this->applyNullableFilter('navision_customer_id', $value);
    }

    public function applyCustomerPriceGroup($value)
    {
        if ($value === null) {
            $value = $this->getCustomer() ? $this->getCustomer()->getNavPriceGroup() : false;
        }

        return $this->applyNullableFilter('customer_price_group', $value);
    }

    public function applyDateStartFilter($value)
    {
        return $this->applyDateFilter('date_start', $value, 'to');
    }

    public function applyDateEndFilter($value)
    {
        return $this->applyDateFilter('date_end', $value, 'from');
    }

    protected function applyNullableFilter($field, $value)
    {
        $params = array(
            array('null' => true),
            array('eq' => ''),
        );
        if ($value) {
            $params[] = array('eq' => $value);
        }
        $this->addFieldToFilter($field, $params);

        return $this;
    }

    protected function applyDateFilter($field, $value, $direction = 'to')
    {
        if ($value === null) {
            $value = date('Y-m-d H:i:s');
        }
        switch ($direction) {
            case 'from':
            case 'to':
                break;
            default:
                throw new LocalizedException(__('Invalid date filter direction'));
        }
        if ($value) {
            $this->addFieldToFilter($field, array(
                array($direction => $value),
                array('null' => true),
            ));
        }

        return $this;
    }

    /**
     * Return logged in customer model
     *
     * @return \Magento\Customer\Model\Customer|null
     */
    protected function getCustomer()
    {
        if (!$this->customer) {
            if ($this->customerSession->isLoggedIn()) {
                $customerId = $this->customerSession->getCustomerId();
                $this->customer = $this->customerRegistry->retrieve($customerId);
            } else {
                return null;
            }
        }
        return $this->customer;
    }
}
