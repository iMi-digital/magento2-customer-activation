<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 * Time: 11:34
 */

namespace IMI\Magento2CustomerActivation\Setup;

use IMI\Magento2CustomerActivation\Model\Attribute\Active;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Customer\Model\Attribute\Backend\Data\Boolean;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * Customer setup factory
     *
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    protected $attributeSetFactory;

    /**
     * InstallData constructor.
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $setup->startSetup();

        // Adding the 'is active' attribute
        $attributesInfo = [
            Active::CUSTOMER_ACCOUNT_ACTIVE =>
                [
                    'type' => 'int',
                    'label' => 'Account is Active',
                    'input' => 'boolean',
                    'backend' => Boolean::class,
                    'position' => 28,
                    'required' => false,
                    'adminhtml_only' => true,
                    'default' => true,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'user_defined' => true,
                    'system' => 0
                ]
        ];

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        foreach ($attributesInfo as $attributeCode => $attributeParams) {
            $customerSetup->addAttribute(Customer::ENTITY, $attributeCode, $attributeParams);
        }

        $newAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, Active::CUSTOMER_ACCOUNT_ACTIVE);
        $newAttribute->addData([
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'used_in_forms' => ['adminhtml_customer'],
        ]);

        $newAttribute->save();

        // Adding the 'activation email send' attribute
        $attributesInfo = [
            Active::CUSTOMER_ACTIVATION_EMAIL_SENT =>
                [
                    'type' => 'int',
                    'label' => 'Activation email sent',
                    'input' => 'boolean',
                    'backend' => Boolean::class,
                    'position' => 29,
                    'required' => false,
                    'adminhtml_only' => true,
                    'default' => false,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'user_defined' => false,
                    'system' => 0
                ]
        ];

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        foreach ($attributesInfo as $attributeCode => $attributeParams) {
            $customerSetup->addAttribute(Customer::ENTITY, $attributeCode, $attributeParams);
        }

        $newAttribute->save();

        $setup->endSetup();
    }
}
