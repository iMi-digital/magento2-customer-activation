<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 23/04/2018
 */
namespace IMI\Magento2CustomerActivation\Model\Attribute;

use Magento\Customer\Api\Data\CustomerInterface;

class Active
{
    const CUSTOMER_ACCOUNT_ACTIVE = 'account_is_active';
    const CUSTOMER_ACTIVATION_EMAIL_SENT = 'account_activation_email_sent';

    /**
     * @param CustomerInterface $customer
     *
     * @return bool
     */
    public function isCustomerActive($customer): bool
    {
        $attribute = $customer->getCustomAttribute(self::CUSTOMER_ACCOUNT_ACTIVE);
        if ($attribute !== null) { // After the installation of the module
            return boolval($attribute->getValue());
        }

        // Before the installation of the module
        return true;
    }
}

