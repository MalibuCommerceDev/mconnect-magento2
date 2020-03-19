<?php

namespace MalibuCommerce\MConnect\Model\Adminhtml\Config\Backend\Cron;

class Export extends \Magento\Framework\App\Config\Value
{

    const CRON_PATH_CONFIG = [

        'malibucommerce_mconnect/customer/scheduled_customers_importexport_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_customer_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_customer_import/run/model',
            'default_cron' => 'malibucommerce_mconnect/customer/cron_expr',
            'is_enable' => 'groups/customer/fields/enable_scheduled_customers_importexport/value'
        ],
        'malibucommerce_mconnect/order/scheduled_orders_export_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_queue_orders_export/schedule/cron_exp',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_queue_orders_export/run/model',
            'default_cron' => 'malibucommerce_mconnect/queue/cron_expr',
            'is_enable' => 'groups/order/fields/enable_scheduled_orders_export/value'
        ],

        'malibucommerce_mconnect/product/scheduled_products_import_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_product_import/schedule/cron_exp',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_product_import/run/model',
            'default_cron' => 'malibucommerce_mconnect/product/cron_expr',
            'is_enable' => 'groups/product/fields/enable_scheduled_products_import/value'
        ],

        'malibucommerce_mconnect/inventory/scheduled_inventorys_import_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_inventory_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_inventory_import/run/model',
            'default_cron' => 'malibucommerce_mconnect/inventory/cron_expr',
            'is_enable' => 'groups/inventory/fields/enable_scheduled_inventorys_import/value'
        ],

        'malibucommerce_mconnect/invoice/scheduled_invoices_import_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_invoice_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_invoice_import/run/model',
            'default_cron' => 'malibucommerce_mconnect/invoice/cron_expr',
            'is_enable' => 'groups/invoice/fields/enable_scheduled_invoices_import/value'
        ],

        'malibucommerce_mconnect/shipment/scheduled_shipments_import_week_days' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_shipment_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_shipment_import/run/model',
            'default_cron' => 'malibucommerce_mconnect/queue/cron_expr',
            'is_enable' => 'groups/shipment/fields/enable_scheduled_shipments_import/value'
        ],
    ];


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
    )
    {
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
        $currentPath = $this->getPath();
        if (empty(self::CRON_PATH_CONFIG[$currentPath])) {
            parent::afterSave();

            return $this;
        }

        $scheduledExportEnabled = $this->getData(self::CRON_PATH_CONFIG[$currentPath]['is_enable']);
        $weekDays = $this->getValue();

        if ($scheduledExportEnabled && !empty($weekDays)) {
            $cronExprArray = [
                '*',                                                            # Minute
                '*',                                                            # Hour
                '*',                                                            # Day of the Month
                '*',                                                            # Month of the Year
                count(explode(',', $weekDays)) == 7 ? '*' : $weekDays,       # Day of the Week
            ];
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = $this->getData(self::CRON_PATH_CONFIG[$currentPath]['default_cron']);
        }

        try {
            $this->configValueFactory->create()
                ->load(self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path'], 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path'])
                ->save();

            $this->configValueFactory->create()
                ->load(self::CRON_PATH_CONFIG[$currentPath]['cron_model_path'], 'path')
                ->setValue('')
                ->setPath(self::CRON_PATH_CONFIG[$currentPath]['cron_model_path'])
                ->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t save the Cron expression.'));
        }

        parent::afterSave();

        return $this;
    }
}