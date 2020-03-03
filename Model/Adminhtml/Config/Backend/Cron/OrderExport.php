<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Backend\Cron;

class OrderExport extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_SCHEDULE_PATH = 'crontab/default/jobs/malibucommerce_mconnect_queue_orders_export/schedule/cron_expr';
    const CRON_STRING_MODEL_PATH = 'crontab/default/jobs/malibucommerce_mconnect_queue_orders_export/run/model';

    const XML_PATH_SCHEDULED_ORDERS_EXPORT_ENABLED    = 'groups/order/fields/enable_scheduled_orders_export/value';
    const XML_PATH_SCHEDULED_ORDERS_EXPORT_WEEKDAYS   = 'groups/order/fields/scheduled_orders_export_week_days/value';
    const XML_PATH_QUEUE_CRON_EXPR                    = 'groups/queue/fields/cron_expr/value';

    /** @var \Magento\Framework\App\Config\ValueFactory */
    protected $configValueFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Cron settings after save
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSave()
    {
        $scheduledExportEnabled = $this->getData(self::XML_PATH_SCHEDULED_ORDERS_EXPORT_ENABLED);
        $weekDays = $this->getData(self::XML_PATH_SCHEDULED_ORDERS_EXPORT_WEEKDAYS);

        if ($scheduledExportEnabled && !empty($weekDays)) {
            $cronExprArray = [
                '*',                                                            # Minute
                '*',                                                            # Hour
                '*',                                                            # Day of the Month
                '*',                                                            # Month of the Year
                count($weekDays) == 7 ? '*' : join(',', $weekDays),       # Day of the Week
            ];
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = $this->getData(self::XML_PATH_QUEUE_CRON_EXPR);
        }

        try {
            $this->configValueFactory->create()
                ->load(self::CRON_STRING_SCHEDULE_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_SCHEDULE_PATH)
                ->save();

            $this->configValueFactory->create()
                ->load(self::CRON_STRING_MODEL_PATH, 'path')
                ->setValue('')
                ->setPath(self::CRON_STRING_MODEL_PATH)
                ->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t save the Cron expression.'));
        }

        parent::afterSave();

        return $this;
    }
}
