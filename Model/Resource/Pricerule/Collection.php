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
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface    $entityFactory
     * @param \Psr\Log\LoggerInterface                                     $logger
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager
     * @param \Magento\Framework\DB\Adapter\AdapterInterface               $connection
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb         $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        Session $customerSession,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->customerSession = $customerSession;
    }

    public function _construct()
    {
        $this->_init('MalibuCommerce\MConnect\Model\Pricerule', 'MalibuCommerce\MConnect\Model\Resource\Pricerule');
    }

    public function applyAllFilters($sku, $qty, $navisionCustomerId = null, $dateStart = null, $dateEnd = null)
    {
        $this->applySkuFilter($sku)
            ->applyQtyFilter($qty)
            ->applyNavisionCustomerIdFilter($navisionCustomerId)
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

    public function applyNavisionCustomerIdFilter($value)
    {
        if ($value === null) {
            $value = $this->getCustomer() ? $this->getCustomer()->getNavId() : null;
            if (!$value) {
                $value = false;
            }
        }

        return $this->applyNullableFilter('navision_customer_id', $value);
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
     * @return \Magento\Customer\Api\Data\CustomerInterface|null
     */
    protected function getCustomer()
    {
        if (!$this->customer) {
            if ($this->customerSession->isLoggedIn()) {
                $this->customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
            } else {
                return null;
            }
        }

        return $this->customer;
    }
}
