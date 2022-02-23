<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 */
namespace IMI\Magento2CustomerActivation\Model;

use Magento\Backend\Helper\Data;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AdminNotification
{
    protected TransportBuilder $transportBuilder;

    protected StoreManagerInterface $storeManager;

    protected ScopeConfigInterface $scopeConfig;

    protected Data $backendHelper;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        Data $backendHelper
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManagerInterface;
        $this->scopeConfig = $scopeConfigInterface;
        $this->backendHelper = $backendHelper;
    }

    /**
     * Send an email to the site owner to notice it that
     * a new customer has registered
     *
     * @param CustomerInterface $customer
     *
     * @throws MailException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function send($customer)
    {
        $siteOwnerEmail = $this->scopeConfig->getValue(
            'trans_email/ident_sales/email',
            ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );

        $emailTemplate = $this->scopeConfig->getValue(
            'customer/create_account/imi_activation_email_notification_template',
            ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );

        if (!$emailTemplate) {
            $emailTemplate = 'imi_activation_email_notification';
        }

        $this->transportBuilder->setTemplateIdentifier($emailTemplate)
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $customer->getStoreId(),
                ]
            )
            ->setTemplateVars([
                'email' => $customer->getEmail(),
                'customer_admin_url' => $this->backendHelper->getUrl('customer/index/edit/id/' . $customer->getId()),
            ]);

        $this->transportBuilder->addTo($siteOwnerEmail);
        $this->transportBuilder->setFrom(
            [
                'name' => $this->storeManager->getStore($customer->getStoreId())->getName(),
                'email' => $siteOwnerEmail,
            ]
        );

        $this->transportBuilder->getTransport()->sendMessage();
    }
}
