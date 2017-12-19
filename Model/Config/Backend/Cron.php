<?php

namespace MalibuCommerce\MConnect\Model\Config\Backend;

class Cron extends \Magento\Framework\App\Config\Value
{
    const CRON_PATH_CONFIG = [
        'malibucommerce_mconnect/queue/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_queue_process/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_queue_process/run/model'
        ],
        'malibucommerce_mconnect/customer/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_customer_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_customer_import/run/model'
        ],
        'malibucommerce_mconnect/product/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_product_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_product_import/run/model'
        ],
        'malibucommerce_mconnect/invoice/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_invoice_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_invoice_import/run/model'
        ],
        'malibucommerce_mconnect/shipment/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_shipment_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_shipment_import/run/model'
        ],
        'malibucommerce_mconnect/price_rule/cron_expr' => [
            'cron_expr_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_pricerule_import/schedule/cron_expr',
            'cron_model_path' => 'crontab/default/jobs/malibucommerce_mconnect_navision_pricerule_import/run/model'
        ],
    ];

    /** @var \Magento\Framework\App\Config\ValueFactory */
    protected $_configValueFactory;

    /**
     * Cron constructor.
     *
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $config
     * @param \Magento\Framework\App\Cache\TypeListInterface               $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory                   $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     *
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_configValueFactory = $configValueFactory;
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
        try {
            $currentPath = $this->getPath();
            if (empty(self::CRON_PATH_CONFIG[$currentPath])) {

                return $this;
            }

            $this->_configValueFactory->create()->load(
                self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path'],
                'path'
            )->setValue(
                $this->getValue()
            )->setPath(
                self::CRON_PATH_CONFIG[$currentPath]['cron_expr_path']
            )->save();

            $this->_configValueFactory->create()->load(
                self::CRON_PATH_CONFIG[$currentPath]['cron_model_path'],
                'path'
            )->setValue(
                ''
            )->setPath(
                self::CRON_PATH_CONFIG[$currentPath]['cron_model_path']
            )->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Can\'t save the Cron expression.'));
        }

        parent::afterSave();

        return $this;
    }
}
