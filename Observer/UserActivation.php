<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 * Time: 11:28
 */
namespace Enrico69\Magento2CustomerActivation\Observer;

use Enrico69\Magento2CustomerActivation\Helper\Data;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use Enrico69\Magento2CustomerActivation\Setup\InstallData;
use Psr\Log\LoggerInterface;
use Enrico69\Magento2CustomerActivation\Model\AdminNotification;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;

class UserActivation implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var AdminNotification
     */
    protected $adminNotification;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var AccountManagementInterface
     */
    protected $accountManagement;

    /** @var Data */
    protected Data $helper;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        AdminNotification $adminNotification,
        Session $customerSession,
        AccountManagementInterface $accountManagement,
        Data $helper
    ) {
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->adminNotification = $adminNotification;
        $this->customerSession = $customerSession;
        $this->accountManagement = $accountManagement;
        $this->helper = $helper;
    }

    /**
     * @param EventObserver $observer
     * @throws InputException
     * @throws LocalizedException
     * @throws InputMismatchException
     * @throws NoSuchEntityException
     * @throws MailException
     */
    public function execute(EventObserver $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        if ($this->helper->isEnabled()) {
            $newCustomer = $this->customerRepository->get($customer->getEmail());
            $newCustomer->setCustomAttribute(InstallData::CUSTOMER_ACCOUNT_ACTIVE, 0);
            $this->customerRepository->save($newCustomer);
            $this->messageManager->addNoticeMessage(__('Your account will be enabled by the site owner soon'));
            $this->customerSession->setRegisterSuccess(true);

            $confirmationStatus = $this->accountManagement->getConfirmationStatus($newCustomer->getId());
            if ($confirmationStatus !== AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $this->adminNotification->send($newCustomer);
            }
        }
    }
}
