<?php
declare(strict_types=1);

namespace IMI\Magento2CustomerActivation\Plugin\Adminhtml;

use Magento\Customer\Controller\Adminhtml\Index\Save;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Customer EAV values keep their value_id on the attribute instance when the customer is loaded.
 *
 * - In admin save, core loads the customer before it sets the store context.
 *   When the store is later changed, Magento uses a different attribute instance (store‑scoped), which has no value_id.
 * - Without value_id, the update becomes a no‑op (UPDATE … WHERE value_id = 0).
 *
 * The plugin sets the store first, so the same attribute instance (with value_id)
 * is used for both load and save. That makes the update succeed.
 */
class SetStoreContextBeforeCustomerLoad
{
    private StoreManagerInterface $storeManager;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function beforeExecute(Save $subject): void
    {
        $customer = (array)$subject->getRequest()->getParam('customer', []);
        $storeId = null;

        if (isset($customer['sendemail_store_id']) && $customer['sendemail_store_id'] !== false
            && $customer['sendemail_store_id'] !== ''
        ) {
            $storeId = (int)$customer['sendemail_store_id'];
        }

        if (!$storeId && !empty($customer['website_id'])) {
            $website = $this->storeManager->getWebsite((int)$customer['website_id']);
            $storeIds = $website->getStoreIds();
            if (!empty($storeIds)) {
                $storeId = (int)reset($storeIds);
            }
        }

        if ($storeId > 0) {
            $this->storeManager->setCurrentStore($storeId);
        }
    }
}
