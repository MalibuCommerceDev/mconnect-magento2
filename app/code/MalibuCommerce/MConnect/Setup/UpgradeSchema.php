<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Customer\Setup;

class UpgradeSchema implements UpgradeSchemaInterface
{

    private $eavSetup;

    public function __construct(EavSetup $eavSetup,
                                \Magento\Eav\Model\Config $eavConfig,
                                ModuleDataSetupInterface $moduleDataSetupInterface,
                                \Magento\Eav\Model\Entity\Attribute\Set $attributeSet,
                                \Magento\Customer\Setup\CustomerSetup $customerSetup)
    {
        $this->eavSetup = $eavSetup;
        $this->eavConfig = $eavConfig;
        $this->moduleDataSetupInterface = $moduleDataSetupInterface;
        $this->attributeSet = $attributeSet;
        $this->customerSetup = $customerSetup;
    }

    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        if (!$context->getVersion()) {
                
            $table = $setup->getConnection()
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

            $setup->getConnection()->createTable($table);



            $table = $setup->getConnection()
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
                ), 'Password');

            $setup->getConnection()
                ->addColumn(
                    $setup->getTable('malibucommerce_mconnect_queue'),
                    'connection_id',
                    array(
                        'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'comment' => 'Connection ID'
                    )
                );

            $setup->getConnection()->createTable($table);



            $setup->getConnection()
                ->addColumn(
                    $setup->getTable('malibucommerce_mconnect_connection'),
                    'rules',
                    array(
                        'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'comment' => 'Rules'
                    )
                );

            $setup->getConnection()
                ->addColumn(
                    $setup->getTable('malibucommerce_mconnect_connection'),
                    'sort_order',
                    array(
                        'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                        'comment' => 'Sort Order'
                    )
                );





            /*$setup->addAttribute('customer_address', 'nav_id', array(
                'type'             => 'varchar',
                'input'            => 'text',
                'label'            => 'NAV ID',
                'global'           => 1,
                'visible'          => 1,
                'required'         => 0,
                'user_defined'     => 0,
                'visible_on_front' => 0
            ));*/


            $customerSetup = $this->customerSetup->create(['setup' => $this->moduleDataSetupInterface]);

            $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer_address');
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();

            $attributeSet = $this->attributeSet;
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $customerSetup->addAttribute('customer_address', 'nav_id', [
                'type' => 'static',
                'label' => 'NAV ID',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'visible_on_front' => false,
                'user_defined' => false,
                'sort_order' => 5,
                'position' => 5,
                'system' => 0,
                'default' => 'DEFAULT'
            ]);

            $attribute = $customerSetup->getEavConfig()->getAttribute('customer_address', 'nav_id')
                ->addData([
                    'attribute_set_id' => $attributeSetId,
                    'attribute_group_id' => $attributeGroupId,
                    'used_in_forms' => ['adminhtml_customer_address', 'customer_address_edit', 'customer_register_address'],
                ]);
            $attribute->save();

            /*
            $eavSetup = $this->eavSetup->create(['setup' => $this->moduleDataSetupInterface]);
            $eavSetup->addAttribute(
                \Magento\Customer\Model\Customer::ENTITY,
                'nav_id',
                [
                    'type' => 'static',
                    'label' => 'NAV ID',
                    'input' => 'text',
                    'required' => false,
                    'default' => '0',
                    'sort_order' => 100,
                    'system' => 0,
                    'position' => 100,
                    'validate_rules' => 'a:2:{s:15:"max_text_length";i:255;s:15:"min_text_length";i:1;}',
                    'is_visible' => 0
                ]
            );
            $customerAttribute = $this->eavConfig->getAttribute(\Magento\Customer\Model\Customer::ENTITY, 'nav_id');
            $customerAttribute->setData(
                'used_in_forms',
                ['adminhtml_customer_address']
            );
            $customerAttribute->save();
            */




            $table = $setup->getConnection()
                ->newTable($setup->getTable('malibucommerce_mconnect_last_sync'))
                ->addColumn('id', \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER, null, array(
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary'  => true,
                ), 'Last Sync ID')
                ->addColumn('name', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, array(
                    'nullable' => false,
                ), 'Name')
                ->addColumn('time', \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, null, array(
                    'nullable' => false,
                ), 'Last Sync Time')
                ->addIndex($setup->getIdxName('malibucommerce_mconnect_last_sync', array('name'), \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
                    array('name'), array('type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE))
            ;

            $setup->getConnection()->createTable($table);

        }


        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            //code to upgrade to 1.0.1
        }

        // reference comment
        /*$sql = "ALTER TABLE `{$setup->getTable('salesrule')}` ADD `max_discount_amount` DECIMAL(12,4) NOT NULL DEFAULT '0.0000' AFTER `discount_amount`;";
        $setup->getConnection()->query($sql);*/

        $installer->endSetup();
    }

}