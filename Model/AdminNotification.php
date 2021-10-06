<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 */
namespace IMI\Magento2CustomerActivation\Model;

use Magento\Backend\Helper\Data;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class AdminNotification
{
    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    protected Data $backendHelper;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        Data $backendHelper
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->backendHelper = $backendHelper;
    }

    /**
     * Send an email to the site owner to notice it that
     * a new customer has registered
     *
     * @param CustomerInterface $customer
     *
     * @throws MailException
     */
    public function send($customer)
    {
        $siteOwnerEmail = $this->scopeConfigInterface->getValue(
            'trans_email/ident_sales/email',
            ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );

        $this->transportBuilder->setTemplateIdentifier('imi_activation_email_notification')
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
                'name' => $this->storeManagerInterface->getStore($customer->getStoreId())->getName(),
                'email' => $siteOwnerEmail,
            ]
        );

        $this->transportBuilder->getTransport()->sendMessage();
    }
}
