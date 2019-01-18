<?php

namespace MalibuCommerce\MConnect\Model\Queue;

class Pricerule extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'price_rule';
    const NAV_XML_NODE_ITEM_NAME = 'sales_price';

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Pricerule
     */
    protected $navPriceRule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * Date
     *
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $date;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Navision\Pricerule $navPriceRule,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Pricerule $rule,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        $this->navPriceRule = $navPriceRule;
        $this->config = $config;
        $this->rule = $rule;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->date = $date;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navPriceRule, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     */
    public function importPriceRule($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $data = [
            'nav_id'               => (int) $data->unique_id,
            'website_id'           => (int) $websiteId,
            'sku'                  => (string) $data->nav_item_id,
            'navision_customer_id' => (string) $data->nav_customer_id,
            'qty_min'              => (int) $data->min_quantity,
            'price'                => (float) $data->unit_price,
            'customer_price_group' => (string) $data->cust_price_group,
            'date_start'           => ((string) $data->start_date) ? date('Y:m:d H:i:s', strtotime((string) $data->start_date)) : null,
            'date_end'             => ((string) $data->end_date) ? date('Y:m:d H:i:s', strtotime((string) $data->end_date)) : null,
        ];

        /** @var \MalibuCommerce\MConnect\Model\Resource\Pricerule\Collection $collection */
        $collection = $this->rule->getCollection()
            ->addFilter('nav_id', (int) $data->unique_id)
            ->addFilter('website_id', (int) $websiteId)
            ->setPageSize(1)
            ->setCurPage(1);

        /** @var \MalibuCommerce\MConnect\Model\Pricerule $model */
        $model = $collection->getFirstItem();
        $isUpdate = $model && $model->getId();
        $model->addData($data);
        try {
            $model->save();
            $this->messages .= 'Price Rule ' . ($isUpdate ? 'UPDATED' : 'CREATED')  . ': NAV ID ' . $model->getNavId();
        } catch (\Throwable $e) {
            $this->messages .= $e->getMessage();
        }
        $this->rule->unsetData();

        return true;
    }
}