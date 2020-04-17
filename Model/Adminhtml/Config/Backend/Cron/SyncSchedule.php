<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Backend\Cron;

use MalibuCommerce\MConnect\Model\Queue;

class SyncSchedule extends \Magento\Framework\App\Config\Value
{
    const CRON_EVERY_MINUTE = 'every minute';
    const CRON_EVERY_HOUR   = 'every hour';
    const CRON_PATH_CONFIG  = [
        'malibucommerce_mconnect/customer/scheduled_customer_import_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_customer_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_customer_import/run/model',
            'default_cron'    => 'malibucommerce_mconnect/customer/cron_expr',
            'is_enabled'      => 'groups/customer/fields/enable_scheduled_customer_import/value',
            'type'            => Queue::ACTION_IMPORT,
            'entity'          => Queue\Customer::CODE
        ],
        'malibucommerce_mconnect/customer/scheduled_customer_export_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_customer_export/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_customer_export/run/model',
            'default_cron'    => 'malibucommerce_mconnect/customer/cron_expr_export',
            'is_enabled'      => 'groups/customer/fields/enable_scheduled_customer_export/value',
            'type'            => Queue::ACTION_EXPORT,
            'entity'          => Queue\Customer::CODE
        ],
        'malibucommerce_mconnect/order/scheduled_order_export_week_days'       => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_order_export/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_order_export/run/model',
            'default_cron'    => 'malibucommerce_mconnect/order/cron_expr_export',
            'is_enabled'      => 'groups/order/fields/enable_scheduled_order_export/value',
            'type'            => Queue::ACTION_EXPORT,
            'entity'          => Queue\Order::CODE
        ],
        'malibucommerce_mconnect/product/scheduled_product_import_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_product_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_product_import/run/model',
            'default_cron'    => 'malibucommerce_mconnect/product/cron_expr',
            'is_enabled'      => 'groups/product/fields/enable_scheduled_product_import/value',
            'type'            => Queue::ACTION_IMPORT,
            'entity'          => Queue\Product::CODE
        ],
        'malibucommerce_mconnect/inventory/scheduled_inventory_import_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_inventory_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_inventory_import/run/model',
            'default_cron'    => 'malibucommerce_mconnect/inventory/cron_expr',
            'is_enabled'      => 'groups/inventory/fields/enable_scheduled_inventory_import/value',
            'type'            => Queue::ACTION_IMPORT,
            'entity'          => Queue\Inventory::CODE
        ],
        'malibucommerce_mconnect/invoice/scheduled_invoice_import_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_invoice_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_invoice_import/run/model',
            'default_cron'    => 'malibucommerce_mconnect/invoice/cron_expr',
            'is_enabled'      => 'groups/invoice/fields/enable_scheduled_invoice_import/value',
            'type'            => Queue::ACTION_IMPORT,
            'entity'          => Queue\Invoice::CODE
        ],
        'malibucommerce_mconnect/shipment/scheduled_shipment_import_week_days' => [
            'cron_expr_path'  => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_shipment_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/malibucommerce_connect/jobs/malibucommerce_mconnect_navision_shipment_import/run/model',
            'default_cron'    => 'malibucommerce_mconnect/queue/cron_expr',
            'is_enabled'      => 'groups/shipment/fields/enable_scheduled_shipment_import/value',
            'type'            => Queue::ACTION_IMPORT,
            'entity'          => Queue\Shipment::CODE
        ]
    ];

    /** @var \Magento\Framework\App\Config\ValueFactory */
    protected $configValueFactory;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->configValueFactory = $configValueFactory;
        $this->scopeConfig = $scopeConfig;
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
        $currentPath = $this->getPath();
        if (empty(self::CRON_PATH_CONFIG[$currentPath])) {
            parent::afterSave();

            return $this;
        }

        $scheduledExportEnabled = $this->getData(self::CRON_PATH_CONFIG[$currentPath]['is_enabled']);
        $weekDays = $this->getValue();

        $action = self::CRON_PATH_CONFIG[$currentPath]['type'];
        $entity = self::CRON_PATH_CONFIG[$currentPath]['entity'];

        $path = 'malibucommerce_mconnect/' . $entity . '/scheduled_' . $entity . '_' . $action . '_start_times';

        $values = $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
            null
        );
        $minutes = '*';

        if (strpos($values, self::CRON_EVERY_HOUR) !== false) {
            $minutes = '0';
        }

        if ($scheduledExportEnabled && !empty($weekDays)) {
            $cronExprArray = [
                $minutes,                                                       # Minute
                '*',                                                            # Hour
                '*',                                                            # Day of the Month
                '*',                                                            # Month of the Year
                count(explode(',', $weekDays)) == 7 ? '*' : $weekDays, # Day of the Week
            ];
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = $this->getData(self::CRON_PATH_CONFIG[$currentPath]['default_cron']);
        }

        if ($cronExprString) {
            try {
                $this->configValueFactory->create()
                    ->load(self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path'], 'path')
                    ->setValue($cronExprString)
                    ->setPath(self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path'])
                    ->save();

                /*$this->configValueFactory->create()
                    ->load(self::CRON_PATH_CONFIG[$currentPath]['cron_model_path'], 'path')
                    ->setValue('')
                    ->setPath(self::CRON_PATH_CONFIG[$currentPath]['cron_model_path'])
                    ->save();*/
            } catch (\Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t save the Cron expression.'));
            }
        }
        parent::afterSave();

        return $this;
    }
}