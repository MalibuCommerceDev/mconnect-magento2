<?php

namespace MalibuCommerce\MConnect\Model\Resource\Pricerule;

use Magento\Framework\Exception\LocalizedException;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface
     */
    protected $customer;

    /**
     * @var \Magento\Customer\Model\CustomerRegistry
     */
    protected $customerRegistry;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var \Magento\Customer\Model\SessionFactory
     */
    protected $customerSessionFactory;

    public function __construct(
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        \Magento\Customer\Model\SessionFactory $customerSessionFactory,
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->customerRegistry = $customerRegistry;
        $this->customerSessionFactory = $customerSessionFactory;
        $this->groupRepository = $groupRepository;
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
     * @param int $websiteId
     *
     * @return string|bool
     */
    public function matchDiscountPrice($sku, $qty, $websiteId = 0)
    {
        $this->applyAllFilters($sku, $qty, $websiteId);
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
     * @param int $websiteId
     *
     * @return $this
     */
    public function applyAllFilters($sku, $qty, $websiteId = 0)
    {
        $this->applySkuFilter($sku)
            ->applyQtyFilter($qty)
            ->applyWebsiteFilter($websiteId)
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
    public function applyWebsiteFilter($value)
    {
        $this->addFieldToFilter('website_id', ['eq' => $value]);

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
    public function getCustomer()
    {
        if (!$this->customer) {
            /** @var \Magento\Customer\Model\Session $customer */
            $customer = $this->customerSessionFactory->create();
            if ($customer->getCustomer() && $customer->getCustomer()->getId()) {
                $this->customer = $this->customerRegistry->retrieve($customer->getCustomer()->getId());
            } else {
                return null;
            }
        }

        return $this->customer;
    }

    /**
     * Retrieve current customer group code
     *
     * @return null|string
     */
    public function getCustomerGroup()
    {
        $groupCode = null;

        try {
            $groupCode = $this->groupRepository->getById($this->getCurrentCustomerGroupId())->getCode();
        } catch (\Exception $e) {

        }

        return $groupCode;
    }

    /**
     * Retrieve current customer group id
     *
     * @return int
     */
    public function getCurrentCustomerGroupId()
    {
        $groupId = \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID;

        try {
            if ($this->getCustomer()) {
                return $this->getCustomer()->getGroupId();
            }
        } catch (\Exception $e) {

        }

        return $groupId;
    }
}
