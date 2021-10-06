<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 17/08/2017
 */
namespace IMI\Magento2CustomerActivation\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use IMI\Magento2CustomerActivation\Setup\InstallData;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    protected $attributeSetFactory;

    /**
     * InstallData constructor.
     * @param \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.1.0') < 0) {
            $this->upgradeToOneOneZero($setup);
        }

        if (version_compare($context->getVersion(), '1.2.1') < 0) {
            $this->upgradeToOneTwoOne($setup);
        }

        if (version_compare($context->getVersion(), '1.4.0') < 0) {
            $this->upgradeToOneFourZero($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     */
    protected function upgradeToOneOneZero($setup)
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $newAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, InstallData::CUSTOMER_ACTIVATION_EMAIL_SENT);
        $newAttribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer'],
        ]);

        $newAttribute->save();
    }

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     */
    protected function upgradeToOneTwoOne($setup)
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $newAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, InstallData::CUSTOMER_ACCOUNT_ACTIVE);
        $newAttribute->setData('is_user_defined', 0);

        $newAttribute->save();
    }

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     */
    protected function upgradeToOneFourZero($setup)
    {
        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $newAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, InstallData::CUSTOMER_ACCOUNT_ACTIVE);
        $newAttribute->setData('is_used_in_grid', 1);
        $newAttribute->setData('is_visible_in_grid', 1);
        $newAttribute->setData('is_filterable_in_grid', 1);

        $newAttribute->save();
    }
}
