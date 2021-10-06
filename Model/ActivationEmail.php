<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 */
namespace IMI\Magento2CustomerActivation\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ActivationEmail
{
    protected TransportBuilder $transportBuilder;

    protected StoreManagerInterface $storeManagerInterface;

    protected ScopeConfigInterface $scopeConfig;

    /**
     * ActivationEmail constructor.
     *
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManagerInterface
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->scopeConfig = $scopeConfigInterface;
    }

    /**
     * If an account is activated, send an email to the user to notice it
     *
     * @param CustomerInterface $customer
     *
     * @throws MailException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function send($customer)
    {
        $emailTemplate = $this->scopeConfig->getValue(
            'customer/create_account/customer_account_activation_confirmation_template',
            ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );

        if (!$emailTemplate) {
            $emailTemplate = 'imi_activation_email';
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
                'prefix' => $customer->getPrefix(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
            ]);

        $this->transportBuilder->addTo($customer->getEmail());
        $this->transportBuilder->setFrom(
            [
                'name'=> $this->storeManagerInterface->getStore($customer->getStoreId())->getName(),
                'email' => $this->scopeConfig->getValue(
                    'trans_email/ident_sales/email',
                    ScopeInterface::SCOPE_STORE,
                    $customer->getStoreId()
                )
            ]
        );

        $this->transportBuilder->getTransport()->sendMessage();
    }
}
