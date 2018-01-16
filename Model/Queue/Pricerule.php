<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\LocalizedException;

class Pricerule extends \MalibuCommerce\MConnect\Model\Queue
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Pricerule
     */
    protected $navPriceRule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Pricerule
     */
    protected $rule;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config|Config
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

    public function importAction()
    {
        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_PRICERULE_SYNC_TIME);
        do {
            $result = $this->navPriceRule->export($page++, $lastUpdated);
            foreach ($result->sales_price as $data) {
                $count++;
                try {
                    $import = $this->importPriceRule($data);
                } catch (\Exception $e) {
                    $this->messages .= $e->getMessage();
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while ($this->hasRecords($result));
        $this->setLastSyncTime(Flag::FLAG_CODE_LAST_PRICERULE_SYNC_TIME, $lastSync);
        $this->messages .= PHP_EOL . 'Processed ' . $count . ' price rule(s).';
    }

    protected function importPriceRule(\SimpleXMLElement $entity)
    {
        $data = [
            'nav_id'               => (int) $entity->unique_id,
            'sku'                  => (string) $entity->nav_item_id,
            'navision_customer_id' => (string) $entity->nav_customer_id,
            'qty_min'              => (int) $entity->min_quantity,
            'price'                => (float) $entity->unit_price,
            'customer_price_group' => (string) $entity->cust_price_group,
            'date_start'           => ((string) $entity->start_date) ? $this->date->gmtDate('Y:m:d H:i:s', $this->date->timestamp((string) $entity->start_date)) : null,
            'date_end'             => ((string) $entity->end_date) ? $this->date->gmtDate('Y:m:d H:i:s', $this->date->timestamp((string) $entity->end_date)) : null,
        ];

        $model = $this->rule->load((int) $entity->unique_id, 'nav_id');
        $model->addData($data);
        try {
            $model->save();
            $this->messages .= 'Price Rule created: ID ' . $model->getId();
        } catch (\Exception $e) {
            $this->messages .= $e->getMessage();
        }
        $this->rule->unsetData();
    }
}