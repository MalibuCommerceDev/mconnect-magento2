<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use MalibuCommerce\MConnect\Model\Queue as QueueModel;
use MalibuCommerce\MConnect\Model\Queue\Order as OrderModel;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<')) {
            $this->install($setup);
        }

        if (version_compare($context->getVersion(), '1.1.1', '<')) {
            $this->upgrade1_1_1($setup);
        }

        if (version_compare($context->getVersion(), '1.1.5', '<')) {
            $this->upgrade1_1_5($setup);
        }

        if (version_compare($context->getVersion(), '1.1.19', '<')) {
            $this->upgrade1_1_19($setup);
        }

        if (version_compare($context->getVersion(), '2.0.0', '<')) {
            $this->upgrade2_0_0($setup);
        }

        if (version_compare($context->getVersion(), '2.2.0', '<')) {
            $this->upgrade2_2_0($setup);
        }

        if (version_compare($context->getVersion(), '2.4.5', '<')) {
            $this->upgrade2_4_5($setup);
        }

        if (version_compare($context->getVersion(), '2.4.6', '<')) {
            $this->upgrade2_4_6($setup);
        }

        if (version_compare($context->getVersion(), '2.6.0', '<=')) {
            $this->upgrade2_6_0($setup);
        }

        if (version_compare($context->getVersion(), '2.6.1', '<=')) {
            $this->upgrade2_6_1($setup);
        }
        if (version_compare($context->getVersion(), '2.7.4', '<=')) {
            $this->upgrade2_7_4($setup);
        }

        if (version_compare($context->getVersion(), '2.9.9', '<=')) {
            $this->upgrade2_9_9($setup);
        }

        $setup->endSetup();
    }

    protected function install(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        /**
         * Create table 'malibucommerce_mconnect_queue'
         */
        $table = $connection
            ->newTable($setup->getTable('malibucommerce_mconnect_queue'))
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ],
                'Queue ID'
            )
            ->addColumn('code', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ], 'Code')
            ->addColumn('action', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ], 'Action')
            ->addColumn('entity_id', Table::TYPE_INTEGER, null, [
                'nullable' => true,
            ], 'Entity ID')
            ->addColumn('connection_id', Table::TYPE_INTEGER, null, [
                'nullable' => true,
            ], 'Connection ID')
            ->addColumn('details', Table::TYPE_TEXT, null, [
                'nullable' => true,
            ], 'Details')
            ->addColumn('status', Table::TYPE_TEXT, 255, [
                'nullable' => false,
            ], 'Status')
            ->addColumn('created_at', Table::TYPE_DATETIME, null, [
                'nullable' => false,
            ], 'Created At')
            ->addColumn('started_at', Table::TYPE_DATETIME, null, [], 'Started At')
            ->addColumn('finished_at', Table::TYPE_DATETIME, null, [], 'Finished At')
            ->addColumn('message', Table::TYPE_TEXT, null, [
                'nullable' => true,
            ], 'Message');
        $connection->createTable($table);

        $table = $connection
            ->newTable($setup->getTable('malibucommerce_mconnect_price_rule'))
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ], 'Rule ID')
            ->addColumn('sku', Table::TYPE_TEXT, 255, [
                'nullable' => true,
            ], 'Sku')
            ->addColumn('navision_customer_id', Table::TYPE_TEXT, 255, [
                'nullable' => true,
            ], 'Navision Customer ID')
            ->addColumn('nav_id', Table::TYPE_INTEGER, null, [
                'nullable' => true,
            ], 'Navision Unique ID')
            ->addColumn('qty_min', Table::TYPE_INTEGER, null, [
                'nullable' => true,
            ], 'Minimum Quantity')
            ->addColumn('price', Table::TYPE_DECIMAL, '12,4', [
                'nullable' => false,
            ], 'Price')
            ->addColumn('date_start', Table::TYPE_DATETIME, null, [
                'nullable' => true,
            ], 'Start Date')
            ->addColumn('date_end', Table::TYPE_DATETIME, null, [
                'nullable' => true,
            ], 'End Date');
        $connection->createTable($table);
        $entityTables = ['sales_order', 'sales_order_grid', 'customer_entity', 'customer_address_entity'];
        foreach ($entityTables as $table) {
            $connection->addColumn(
                $setup->getTable($table),
                'nav_id',
                [
                    'type'    => Table::TYPE_TEXT,
                    'length'  => 255,
                    'comment' => 'NAV ID'
                ]
            );
        }
    }

    protected function upgrade1_1_1(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_price_rule'),
            'customer_price_group',
            [
                'type'    => Table::TYPE_TEXT,
                'length'  => 255,
                'comment' => 'Customer Price Group'
            ]
        );
    }

    protected function upgrade1_1_5(SchemaSetupInterface $setup)
    {
        $entityTables = ['sales_order_address', 'quote_address'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'nav_id',
                [
                    'type'    => Table::TYPE_TEXT,
                    'length'  => 255,
                    'comment' => 'NAV ID'
                ]
            );
        }
    }

    protected function upgrade1_1_19(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'scheduled_at',
            [
                'type'    => Table::TYPE_DATETIME,
                'comment' => 'Scheduled At',
                'after'   => 'created_at'
            ]
        );

        $setup->getConnection()->update(
            $setup->getTable('malibucommerce_mconnect_queue'),
            ['scheduled_at' => new \Zend_Db_Expr('created_at')]
        );
    }

    protected function upgrade2_0_0(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'website_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Website ID',
                'after'   => 'entity_id',
                'default' => 0
            ]
        );

        $setup->getConnection()->dropColumn($setup->getTable('malibucommerce_mconnect_queue'), 'connection_id');

        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_price_rule'),
            'website_id',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'Website ID',
                'after'   => 'nav_id',
                'default' => 0
            ]
        );

        if ($setup->getConnection()->isTableExists('malibucommerce_mconnect_connection')) {
            $setup->getConnection()->dropTable('malibucommerce_mconnect_connection');
        }
    }

    protected function upgrade2_2_0(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->modifyColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'message',
            [
                'type'   => Table::TYPE_TEXT,
                'length' => 16777200
            ]
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'nav_page_num',
            [
                'type'    => Table::TYPE_INTEGER,
                'comment' => 'NAV Data Page Number (chunk number)',
                'after'   => 'website_id',
                'default' => 0
            ]
        );
    }

    protected function upgrade2_4_5(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'logs',
            [
                'type'    => Table::TYPE_TEXT,
                'length'  => Table::MAX_TEXT_SIZE,
                'comment' => 'Request Logs',
                'after'   => 'message',
            ]
        );
    }

    protected function upgrade2_4_6(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['code']),
            ['code']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['website_id']),
            ['website_id']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['action']),
            ['action']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['status']),
            ['status']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['finished_at']),
            ['finished_at']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName(
                'malibucommerce_mconnect_queue',
                ['code', 'action', 'website_id', 'status', 'nav_page_num']
            ),
            ['code', 'action', 'website_id', 'status', 'nav_page_num']
        );
        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['entity_id', 'code', 'status', 'action']),
            ['entity_id', 'code', 'status', 'action']
        );
    }

    protected function upgrade2_6_0(SchemaSetupInterface $setup)
    {
        $entityTables = ['sales_creditmemo', 'sales_creditmemo_grid'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'nav_id',
                [
                    'type'    => Table::TYPE_TEXT,
                    'length'  => 255,
                    'comment' => 'NAV ID'
                ]
            );
        }
    }

    protected function upgrade2_6_1(SchemaSetupInterface $setup)
    {
        $entityTables = ['malibucommerce_mconnect_queue'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'retry_count',
                [
                    'type'    => Table::TYPE_SMALLINT,
                    'after'   => 'logs',
                    'comment' => 'Retry count',
                    'default' => 0
                ]
            );
        }

        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['status', 'code', 'action', 'retry_count']),
            ['status', 'code', 'action', 'retry_count']
        );

        $monthAgo = date("y-m-d", strtotime("-1 month"));
        $setup->getConnection()->update(
            $setup->getTable('malibucommerce_mconnect_queue'),
            ['retry_count' => 5],
            [
                'status = ?'      => QueueModel::STATUS_ERROR,
                'code = ?'        => OrderModel::CODE,
                'action = ?'      => QueueModel::ACTION_EXPORT,
                'created_at <= ?' => $monthAgo
            ]
        );
    }

    protected function upgrade2_7_4(SchemaSetupInterface $setup)
    {
        $entityTables = ['malibucommerce_mconnect_queue'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'entity_increment_id',
                [
                    'type'    => Table::TYPE_TEXT,
                    'after'   => 'entity_id',
                    'comment' => 'Increment ID',
                    'length'  => 255
                ]
            );
        }

        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName(
                'malibucommerce_mconnect_queue',
                ['status', 'code', 'action', 'entity_id', 'entity_increment_id']
            ),
            ['status', 'code', 'action', 'entity_id', 'entity_increment_id']
        );

        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['entity_increment_id']),
            ['entity_increment_id']
        );
    }

    protected function upgrade2_9_9(SchemaSetupInterface $setup)
    {
        $entityTables = ['malibucommerce_mconnect_queue'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'affected_entities_cnt',
                [
                    'type'    => Table::TYPE_INTEGER,
                    'after'   => 'message',
                    'comment' => 'Count of affected Magento entities'
                ]
            );
        }

        $setup->getConnection()->addIndex(
            $setup->getTable('malibucommerce_mconnect_queue'),
            $setup->getIdxName('malibucommerce_mconnect_queue', ['affected_entities_cnt']),
            ['affected_entities_cnt']
        );
    }

}
