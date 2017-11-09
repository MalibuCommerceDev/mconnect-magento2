<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<=')) {
            $this->install($setup);
        }

        if (version_compare($context->getVersion(), '1.1.1', '<=')) {
            $this->upgrade1_1_1($setup);
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
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                array(
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ), 'Queue ID')
            ->addColumn('code', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Code')
            ->addColumn('action', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Action')
            ->addColumn('entity_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Entity ID')
            ->addColumn('connection_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Connection ID')
            ->addColumn('details', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, null, array(
                'nullable' => true,
            ), 'Details')
            ->addColumn('status', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Status')
            ->addColumn('created_at', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(
                'nullable' => false,
            ), 'Created At')
            ->addColumn('started_at', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(

            ), 'Started At')
            ->addColumn('finished_at', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(

            ), 'Finished At')
            ->addColumn('message', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, null, array(
                'nullable' => true,
            ), 'Message');
        $connection->createTable($table);

        /**
         * Create table 'malibucommerce_mconnect_connection'
         */
        $table = $connection
            ->newTable($setup->getTable('malibucommerce_mconnect_connection'))
            ->addColumn('id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ), 'Connection ID')
            ->addColumn('name', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Name')
            ->addColumn('url', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'URL')
            ->addColumn('username', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Username')
            ->addColumn('password', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => false,
            ), 'Password')
            ->addColumn('rules', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, null, array(
                'nullable' => false,
            ), 'Rules')
            ->addColumn('sort_order', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, null, array(
                'nullable' => false,
            ), 'Sort Order');
        $connection->createTable($table);

        $table = $connection
            ->newTable($setup->getTable('malibucommerce_mconnect_price_rule'))
            ->addColumn('id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary'  => true,
            ), 'Rule ID')
            ->addColumn('sku', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => true,
            ), 'Sku')
            ->addColumn('navision_customer_id', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                'nullable' => true,
            ), 'Navision Customer ID')
            ->addColumn('nav_id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Navision Unique ID')
            ->addColumn('qty_min', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                'nullable' => true,
            ), 'Minimum Quantity')
            ->addColumn('price', \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL, '12,4', array(
                'nullable' => false,
            ), 'Price')
            ->addColumn('date_start', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(
                'nullable' => true,
            ), 'Start Date')
            ->addColumn('date_end', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(
                'nullable' => true,
            ), 'End Date');
        $connection->createTable($table);
        $entityTables = ['sales_order', 'sales_order_grid', 'customer_entity', 'customer_address_entity'];
        foreach ($entityTables as $table) {
            $connection->addColumn(
                $setup->getTable($table),
                'nav_id',
                array(
                    'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
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
                'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'  => 255,
                'comment' => 'Customer Price Group'
            )
        );
    }
}
