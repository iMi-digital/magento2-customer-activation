<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 */
namespace IMI\Magento2CustomerActivation\Model;

use Magento\Backend\Helper\Data;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Block\Address\Renderer\DefaultRenderer as AddressRenderer;
use Magento\Customer\Helper\View as CustomerViewHelper;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Customer\Model\AddressRegistry;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdminNotification
{
    protected TransportBuilder $transportBuilder;

    protected StoreManagerInterface $storeManager;

    protected ScopeConfigInterface $scopeConfig;

    protected Data $backendHelper;

    protected DataObjectProcessor $dataObjectProcessor;

    protected CustomerViewHelper $customerViewHelper;

    private AddressRegistry $addressRegistry;

    private AddressConfig $addressConfig;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        Data $backendHelper,
        DataObjectProcessor $dataObjectProcessor,
        CustomerViewHelper $customerViewHelper,
        AddressRegistry $addressRegistry,
        AddressConfig $addressConfig
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManagerInterface;
        $this->scopeConfig = $scopeConfigInterface;
        $this->backendHelper = $backendHelper;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->customerViewHelper = $customerViewHelper;
        $this->addressRegistry = $addressRegistry;
        $this->addressConfig = $addressConfig;
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
                'customer' => $this->getCustomerData($customer),
                'customer_admin_url' => $this->backendHelper->getUrl('customer/index/edit/id/' . $customer->getId()),
                'formattedShippingAddress' => $this->getFormattedShippingAddress($customer),
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

    private function getCustomerData(CustomerInterface $customer): array
    {
        $customerData = $this->dataObjectProcessor->buildOutputDataArray($customer, CustomerInterface::class);
        $customerData['name'] = $this->customerViewHelper->getCustomerName($customer);

        return $customerData;
    }

    private function getFormattedShippingAddress(CustomerInterface $customer): ?string
    {
        if ($customer->getDefaultShipping() === null) {
            return null;
        }

        $address = $this->addressRegistry->retrieve((int)$customer->getDefaultShipping());
        $formatDataObject = $this->addressConfig->getFormatByCode('html');

        /** @var AddressRenderer $renderer */
        $renderer = $formatDataObject->getData('renderer');
        $format = $formatDataObject->getData('default_format');

        return $renderer->render($address, $format);
    }
}
