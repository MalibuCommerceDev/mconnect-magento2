<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encryptor;

    /**
     * UpgradeData constructor.
     *
     * @param CustomerSetupFactory $customerSetupFactory
     * @param EavSetupFactory      $eavSetupFactory
     * @param WriterInterface      $configWriter
     * @param EncryptorInterface   $encryptor
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        EavSetupFactory $eavSetupFactory,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->upgrade1_0_1($setup);
        }

        if (version_compare($context->getVersion(), '1.1.6', '<')) {
            $this->upgrade1_1_6($setup);
        }

        if (version_compare($context->getVersion(), '1.1.17', '<')) {
            $this->upgrade1_1_17($setup, $context);
        }

        if (version_compare($context->getVersion(), '1.1.19', '<')) {
            $this->upgrade1_1_19($setup);
        }

        if (version_compare($context->getVersion(), '1.1.42', '<')) {
            $this->upgrade1_1_42($setup);
        }

        if (version_compare($context->getVersion(), '1.1.55', '<')) {
            $this->upgrade1_1_55($setup);
        }

        $setup->endSetup();
    }

    protected function upgrade1_0_1(ModuleDataSetupInterface $setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerSetup->addAttribute(Customer::ENTITY, 'nav_id', array(
            'label'                 => 'Customer NAV ID',
            'type'                  => 'static',
            'input'                 => 'text',
            'global'                => ScopedAttributeInterface::SCOPE_GLOBAL,
            'position'              => 1000,
            'visible'               => true,
            'required'              => false,
            'user_defined'          => false,
            'system'                => false,
            'is_used_in_grid'       => true,
            'is_visible_in_grid'    => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true,
        ));

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $entityTypeId = $eavSetup->getEntityTypeId(Customer::ENTITY);
        $attributeSetId = $eavSetup->getDefaultAttributeSetId($entityTypeId);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

        $attribute = $eavSetup->getAttribute($entityTypeId, 'nav_id');
        if ($attribute) {
            $eavSetup->addAttributeToGroup(
                $entityTypeId,
                $attributeSetId,
                $attributeGroupId,
                $attribute['attribute_id'],
                1000
            );
        }

        $customerSetup->addAttribute('customer_address', 'nav_id', [
            'type'             => 'static',
            'label'            => 'Customer Address NAV ID',
            'input'            => 'text',
            'global'           => ScopedAttributeInterface::SCOPE_GLOBAL,
            'required'         => false,
            'visible'          => true,
            'visible_on_front' => false,
            'user_defined'     => false,
            'position'         => 5,
            'system'           => 0,
            'default'          => 'DEFAULT'
        ]);

        $entityTypeId = $eavSetup->getEntityTypeId('customer_address');
        $attributeSetId = $eavSetup->getDefaultAttributeSetId($entityTypeId);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

        $attribute = $eavSetup->getAttribute($entityTypeId, 'nav_id');
        if ($attribute) {
            $eavSetup->addAttributeToGroup(
                $entityTypeId,
                $attributeSetId,
                $attributeGroupId,
                $attribute['attribute_id'],
                1000
            );
        }
    }

    protected function upgrade1_1_6(ModuleDataSetupInterface $setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $attributes = array(
            'nav_price_group'   => array(
                'label'                 => 'Navision Price Group',
                'type'                  => 'static',
                'input'                 => 'text',
                'global'                => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'               => true,
                'required'              => false,
                'user_defined'          => false,
                'system'                => false,
                'position'              => 1001,
                'is_used_in_grid'       => false,
                'is_visible_in_grid'    => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            ),
            'nav_payment_terms' => array(
                'label'                 => 'Navision Payment Terms',
                'type'                  => 'static',
                'input'                 => 'textarea',
                'global'                => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'               => true,
                'required'              => false,
                'user_defined'          => false,
                'system'                => false,
                'position'              => 1002,
                'is_used_in_grid'       => false,
                'is_visible_in_grid'    => false,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            )
        );

        $entityTypeId = $eavSetup->getEntityTypeId(Customer::ENTITY);
        $attributeSetId = $eavSetup->getDefaultAttributeSetId($entityTypeId);
        $attributeGroupId = $eavSetup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

        foreach ($attributes as $attribute => $attributeConfig) {
            $customerSetup->addAttribute(Customer::ENTITY, $attribute, $attributeConfig);

            $attribute = $eavSetup->getAttribute($entityTypeId, $attribute);
            if ($attribute) {
                $eavSetup->addAttributeToGroup(
                    $entityTypeId,
                    $attributeSetId,
                    $attributeGroupId,
                    $attribute['attribute_id'],
                    1000
                );
            }
        }

        /**
         * Add required static columns to customer entity DB table
         */
        $setup->getConnection()->addColumn(
            $setup->getTable('customer_entity'),
            'nav_price_group',
            array(
                'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'  => 255,
                'comment' => 'NAV Price Group'
            )
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('customer_entity'),
            'nav_payment_terms',
            array(
                'type'    => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length'  => '64k',
                'comment' => 'NAV Payment Terms'
            )
        );
    }

    protected function upgrade1_1_17(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var \Magento\Customer\Setup\CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        /**
         * Fix nav_price_group and nav_payment_terms attributes
         */
        if (version_compare($context->getVersion(), '1.1.17', '<') && version_compare($context->getVersion(), '1.1.6', '>')) {
            // Needed to reset foreign checks flag
            $setup->endSetup();

            /**
             * Preserve attributes values
             */
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'nav_price_group');
            $select = $setup->getConnection()->select()
                ->from($attribute->getBackend()->getTable(), ['entity_id', 'value'])
                ->where('attribute_id = ?', $attribute->getAttributeId());
            $priceGroupValues = $setup->getConnection()->fetchPairs($select);

            $attribute = $customerSetup->getAttribute(Customer::ENTITY, 'nav_payment_terms');
            $paymentTermsBackendType = $attribute['backend_type'];
            if ($paymentTermsBackendType == 'text') {
                $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'nav_payment_terms');
                $select = $setup->getConnection()->select()
                    ->from($attribute->getBackend()->getTable(), ['entity_id', 'value'])
                    ->where('attribute_id = ?', $attribute->getAttributeId());
                $paymentTermsValues = $setup->getConnection()->fetchPairs($select);
            }

            /**
             * Remove old attributes
             */
            $customerSetup->removeAttribute(Customer::ENTITY, 'nav_price_group');
            $customerSetup->removeAttribute(Customer::ENTITY, 'nav_payment_terms');

            /**
             * Add updated attributes, add required static columns to customer entity DB table
             */
            $this->upgrade1_0_1($setup);
            $this->upgrade1_1_6($setup);

            /**
             * Move attribute values
             */
            if (!empty($priceGroupValues)) {
                foreach ($priceGroupValues as $entityId => $value) {
                    $setup->getConnection()->update('customer_entity', ['nav_price_group' => $value], ['entity_id = ?' => $entityId]);
                }
            }
            if (!empty($paymentTermsValues)) {
                foreach ($paymentTermsValues as $entityId => $value) {
                    $setup->getConnection()->update('customer_entity', ['nav_payment_terms' => $value], ['entity_id = ?' => $entityId]);
                }
            }

            // Needed to reset foreign checks flag
            $setup->startSetup();
        }
    }

    protected function upgrade1_1_19(ModuleDataSetupInterface $setup)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Sales\Model\Order\Status $status */
        $status = $objectManager->get(\Magento\Sales\Model\Order\Status::class);
        $status->setData('status', 'nav_preparing_for_shipment')->setData('label', 'Preparing for Shipment')->save();
        $status->assignState(\Magento\Sales\Model\Order::STATE_NEW, false, true);
    }

    protected function upgrade1_1_42(ModuleDataSetupInterface $setup)
    {
        /** @var \Magento\Customer\Setup\CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $this->upgrade1_0_1($setup);
        $this->upgrade1_1_6($setup);

        $attributes = ['nav_id', 'nav_price_group', 'nav_payment_terms'];
        foreach ($attributes as $attributeCode) {
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);
            $attribute->setData('used_in_forms', ['adminhtml_customer']);
            $attribute->save();
        }
    }

    protected function upgrade1_1_55(ModuleDataSetupInterface $setup)
    {
        /**
         * Update config path
         */
        $oldPath = 'malibucommerce_mconnect/customer/default_nav_id';
        $newPath = 'malibucommerce_mconnect/customer/default_nav_id_magento_registered';
        $setup->getConnection()->update('core_config_data', ['path' => $newPath], ['path = ?' => $oldPath]);

        /**
         * Encrypt NAV connection password
         */
        $select = $setup->getConnection()
            ->select()
            ->from('core_config_data', 'value')
            ->where('path = ?', 'malibucommerce_mconnect/nav_connection/password');

        $password = $setup->getConnection()->fetchOne($select);
        // Don't save value, if an obscured value was received
        if (!preg_match('/^\*+$/', $password) && !empty($password)) {
            $password = $this->encryptor->encrypt($password);
        }

        $this->configWriter->save('malibucommerce_mconnect/nav_connection/password', $password);
    }
}
