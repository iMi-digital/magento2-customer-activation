<?php

namespace IMI\Magento2CustomerActivation\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Data
{
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled($storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'customer/create_account/customer_account_activation',
            ScopeInterface::SCOPE_STORE, $storeId
        );
    }
}
