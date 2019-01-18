<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

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
            ->addColumn('id',
                Table::TYPE_INTEGER,
                null,
                array(
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ), 'Queue ID')
            ->addColumn('code', Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Code')
            ->addColumn('action', Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Action')
            ->addColumn('entity_id', Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Entity ID')
            ->addColumn('connection_id', Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Connection ID')
            ->addColumn('details', Table::TYPE_TEXT, null, array(
                'nullable' => true,
            ), 'Details')
            ->addColumn('status', Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Status')
            ->addColumn('created_at', Table::TYPE_DATETIME, null, array(
                'nullable' => false,
            ), 'Created At')
            ->addColumn('started_at', Table::TYPE_DATETIME, null, array(), 'Started At')
            ->addColumn('finished_at', Table::TYPE_DATETIME, null, array(), 'Finished At')
            ->addColumn('message', Table::TYPE_TEXT, null, array(
                'nullable' => true,
            ), 'Message');
        $connection->createTable($table);

        $table = $connection
            ->newTable($setup->getTable('malibucommerce_mconnect_price_rule'))
            ->addColumn('id', Table::TYPE_INTEGER, null, array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ), 'Rule ID')
            ->addColumn('sku', Table::TYPE_TEXT, 255, array(
                'nullable' => true,
            ), 'Sku')
            ->addColumn('navision_customer_id', Table::TYPE_TEXT, 255, array(
                'nullable' => true,
            ), 'Navision Customer ID')
            ->addColumn('nav_id', Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Navision Unique ID')
            ->addColumn('qty_min', Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Minimum Quantity')
            ->addColumn('price', Table::TYPE_DECIMAL, '12,4', array(
                'nullable' => false,
            ), 'Price')
            ->addColumn('date_start', Table::TYPE_DATETIME, null, array(
                'nullable' => true,
            ), 'Start Date')
            ->addColumn('date_end', Table::TYPE_DATETIME, null, array(
                'nullable' => true,
            ), 'End Date');
        $connection->createTable($table);
        $entityTables = ['sales_order', 'sales_order_grid', 'customer_entity', 'customer_address_entity'];
        foreach ($entityTables as $table) {
            $connection->addColumn(
                $setup->getTable($table),
                'nav_id',
                array(
                    'type'    => Table::TYPE_TEXT,
                    'length'  => 255,
                    'comment' => 'NAV ID'
                )
            );
        }
    }

    protected function upgrade1_1_1(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_price_rule'),
            'customer_price_group',
            array(
                'type'    => Table::TYPE_TEXT,
                'length'  => 255,
                'comment' => 'Customer Price Group'
            )
        );
    }

    protected function upgrade1_1_5(SchemaSetupInterface $setup)
    {
        $entityTables = ['sales_order_address', 'quote_address'];
        foreach ($entityTables as $table) {
            $setup->getConnection()->addColumn(
                $setup->getTable($table),
                'nav_id',
                array(
                    'type'    => Table::TYPE_TEXT,
                    'length'  => 255,
                    'comment' => 'NAV ID'
                )
            );
        }
    }

    protected function upgrade1_1_19(SchemaSetupInterface $setup)
    {
        $setup->getConnection()->addColumn(
            $setup->getTable('malibucommerce_mconnect_queue'),
            'scheduled_at',
            array(
                'type'    => Table::TYPE_DATETIME,
                'comment' => 'Scheduled At',
                'after'   => 'created_at'
            )
        );

        $setup->getConnection()->update(
            'malibucommerce_mconnect_queue',
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

        $setup->getConnection()->dropColumn('malibucommerce_mconnect_queue', 'connection_id');

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
                'type' => Table::TYPE_TEXT,
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
}
