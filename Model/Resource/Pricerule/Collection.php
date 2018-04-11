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
     * Collection constructor.
     *
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface    $entityFactory
     * @param \Psr\Log\LoggerInterface                                     $logger
     * @param \Magento\Customer\Model\CustomerRegistry                     $customerRegistry
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager
     * @param Session                                                      $customerSession
     * @param \Magento\Framework\DB\Adapter\AdapterInterface|null          $connection
     * @param \Magento\Framework\Model\ResourceModel\Db\AbstractDb|null    $resource
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

    /**
     * Match and retrieve discount price by specified product and QTY
     *
     * @param string $sku
     * @param int $qty
     *
     * @return string|bool
     */
    public function matchDiscountPrice($sku, $qty)
    {
        $this->applyAllFilters($sku, $qty);
        $select = clone $this->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns('price', 'main_table');

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * Apply all filters to match "cheapest" discounted price for given product SKU and QTY
     *
     * @param string $sku
     * @param int $qty
     *
     * @return $this
     */
    public function applyAllFilters($sku, $qty)
    {
        $this->applySkuFilter($sku)
            ->applyQtyFilter($qty)
            ->applyCustomerFilter()
            ->applyFromToDateFilter()
            ->setOrder('price', self::SORT_ORDER_ASC)
            ->setPageSize(1)
            ->setCurPage(1);

        return $this;
    }

    /**
     * Apply product SKU filter
     *
     * @param string $value
     *
     * @return $this
     */
    public function applySkuFilter($value)
    {
        $this->addFieldToFilter('sku', ['eq' => $value]);

        return $this;
    }

    /**
     * Apply product QTY filter
     *
     * @param int $value
     *
     * @return $this
     */
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

    /**
     * Apply currently logged in customer filter (customer NAV ID and NAV price group)
     *
     * @return $this
     */
    public function applyCustomerFilter()
    {
        if ($this->getCustomer()) {
            $this->addFieldToFilter(
                array('navision_customer_id', 'customer_price_group'),
                array(
                    array('eq' => $this->getCustomer()->getNavId()),
                    array('eq' => $this->getCustomer()->getNavPriceGroup())
                )
            );
        } else {
            $this->addFieldToFilter('navision_customer_id',
                array(
                    array('null' => true),
                    array('eq' => ''),
                )
            );
            $this->addFieldToFilter('customer_price_group',
                array(
                    array('null' => true),
                    array('eq' => ''),
                )
            );
        }

        return $this;
    }

    /**
     * Apply price rule from/to dates filter
     *
     * @return $this
     */
    public function applyFromToDateFilter()
    {
        $this->applyDateFilter('date_start', null, 'to');
        $this->applyDateFilter('date_end', null, 'from');

        return $this;
    }

    /**
     * Apply price rule date filter
     *
     * @param string $field
     * @param string|null $value
     * @param string $direction
     *
     * @return $this
     * @throws LocalizedException
     */
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
