<?php

namespace MalibuCommerce\MConnect\Model\ResourceModel\Pricerule;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MalibuCommerce\MConnect\Helper\Customer;
use MalibuCommerce\MConnect\Model\Pricerule;

class Collection extends AbstractCollection
{
    /** @var Customer */
    protected $customerHelper;

    /**
     * Collection constructor.
     *
     * @param Customer                 $customerHelper
     * @param EntityFactoryInterface   $entityFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param FetchStrategyInterface   $fetchStrategy
     * @param ManagerInterface         $eventManager
     * @param AdapterInterface|null    $connection
     * @param AbstractDb|null          $resource
     */
    public function __construct(
        Customer $customerHelper,
        EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        $this->customerHelper = $customerHelper;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    public function _construct()
    {
        $this->_init(Pricerule::class, \MalibuCommerce\MConnect\Model\ResourceModel\Pricerule::class);
    }

    /**
     * Match and retrieve discount price by specified product and QTY
     *
     * @param string $sku
     * @param int    $qty
     * @param int    $websiteId
     *
     * @return string|bool
     */
    public function matchDiscountPrice($sku, $qty, $websiteId = 0)
    {
        $this->applyAllFilters($sku, $qty, $websiteId);
        $select = clone $this->getSelect();
        $select->reset(Select::COLUMNS);
        $select->columns('price', 'main_table');
        if ($websiteId != 0) {
            $select->order(new \Zend_Db_Expr(sprintf(
                'FIELD(main_table.website_id,%s)',
                implode(',', [$websiteId, 0])
            )));
        }
        $select->order(new \Zend_Db_Expr('main_table.price ' . self::SORT_ORDER_ASC));

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * Apply all filters to match "cheapest" discounted price for given product SKU and QTY
     *
     * @param string $sku
     * @param int    $qty
     * @param int    $websiteId
     *
     * @return $this
     */
    public function applyAllFilters($sku, $qty, $websiteId = 0)
    {
        $this->applySkuFilter($sku)
            ->applyQtyFilter($qty)
            ->applyWebsiteFilter($websiteId)
            ->applyCustomerCurrencyFilter()
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
     * @param int $value
     *
     * @return $this
     */
    public function applyWebsiteFilter($value)
    {
        $this->addFieldToFilter(
            'website_id',
            $value != 0
                ? ['in' => [0, $value]]
                : ['eq' => $value]
        );

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
            $this->addFieldToFilter('qty_min', [
                ['to' => $value],
                ['null' => true],
            ]);
        }

        return $this;
    }

    /**
     * Apply customer price currency filter
     *
     * @return $this
     */
    public function applyCustomerCurrencyFilter()
    {
        $customer = $this->customerHelper->getCurrentCustomer();
        if ($customer) {
            $currencyCode = !empty($customer->getNavCurrencyCode())
                ? $customer->getNavCurrencyCode()
                : \MalibuCommerce\MConnect\Model\Queue\Pricerule::DEFAULT_CUSTOMER_CURRENCY_CODE;
            $this->addFieldToFilter('currency_code',
                [
                    ['eq' => $currencyCode],
                    ['null' => true],
                    ['eq' => ''],
                ]
            );

            return $this;
        }

        $this->addFieldToFilter('currency_code',
            [
                ['null' => true],
                ['eq' => ''],
            ]
        );

        return $this;
    }

    /**
     * Apply currently logged in customer filter (customer NAV ID and NAV price group)
     *
     * @return $this
     */
    public function applyCustomerFilter()
    {
        $customer = $this->customerHelper->getCurrentCustomer();
        if ($customer) {
            $this->getSelect()->where(sprintf(
                '(%s) OR (%s)',
                sprintf(
                    '(%s) OR (%s)',
                    $this->getConnection()->quoteInto(
                        'main_table.navision_customer_id = ?',
                        $customer->getNavId()
                    ),
                    $this->getConnection()->quoteInto(
                        'main_table.customer_price_group = ?',
                        $customer->getNavPriceGroup()
                    )
                ),
                sprintf(
                    '(%s) AND (%s)',
                    sprintf(
                        '(%s) OR (%s)',
                        'main_table.navision_customer_id IS NULL',
                        'main_table.navision_customer_id = ""'
                    ),
                    sprintf(
                        '(%s) OR (%s)',
                        'main_table.customer_price_group IS NULL',
                        'main_table.customer_price_group = ""'
                    )
                )
            ));
        } else {
            $this->addFieldToFilter(
                'navision_customer_id',
                [
                    ['null' => true],
                    ['eq' => ''],
                ]
            );
            $this->addFieldToFilter(
                'customer_price_group',
                [
                    ['null' => true],
                    ['eq' => ''],
                ]
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
     * @param string      $field
     * @param string|null $value
     * @param string      $direction
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
            $this->addFieldToFilter($field, [
                [$direction => $value],
                ['null' => true],
            ]);
        }

        return $this;
    }
}
