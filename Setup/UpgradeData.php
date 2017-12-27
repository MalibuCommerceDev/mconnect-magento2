<?php

namespace MalibuCommerce\MConnect\Setup;

use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Init
     *
     * @param PageFactory $pageFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

            $customerSetup->addAttribute(\Magento\Customer\Model\Customer::ENTITY, 'nav_id', array(
                'label'        => 'Customer NAV ID',
                'type'         => 'static',
                'input'        => 'text',
                'global'       => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'      => true,
                'required'     => false,
                'user_defined' => true,
            ));

            /** @var EavSetup $eavSetup */
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $entityTypeId = $eavSetup->getEntityTypeId(\Magento\Customer\Model\Customer::ENTITY);
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
                'global'           => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'required'         => false,
                'visible'          => true,
                'visible_on_front' => false,
                'user_defined'     => false,
                'sort_order'       => 5,
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

        if (version_compare($context->getVersion(), '1.1.6', '<=')) {
            $this->upgrade1_1_6($setup);
        }

        $setup->endSetup();
    }

    protected function upgrade1_1_6(ModuleDataSetupInterface $setup)
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $customerSetup->addAttribute(\Magento\Customer\Model\Customer::ENTITY, 'mconnect_status', array(
            'label' => 'MConnect Status',
            'type' => 'static',
            'input' => 'text',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => true,
        ));
    }
}
