<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 * Time: 11:29
 */
namespace IMI\Magento2CustomerActivation\Observer;

use IMI\Magento2CustomerActivation\Helper\Data;
use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use IMI\Magento2CustomerActivation\Model\ActivationEmail;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\MailException;
use IMI\Magento2CustomerActivation\Model\Attribute\Active;

class UserEdition implements ObserverInterface
{
    protected LoggerInterface $logger;

    protected CustomerRepositoryInterface $customerRepository;

    protected ManagerInterface $messageManager;

    protected ActivationEmail $activationEmail;

    protected AdapterInterface $connection;

    protected Active $activeAttribute;

    protected Data $helper;

    public function __construct(
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        ActivationEmail $activationEmail,
        ResourceConnection $resourceConnection,
        Active $activeAttribute,
        Data $helper
    ) {
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->activationEmail = $activationEmail;
        $this->connection = $resourceConnection->getConnection();
        $this->activeAttribute = $activeAttribute;
        $this->helper = $helper;
    }

    /**
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer)
    {
        /** @var CustomerInterface $customer */
        $customer = $observer->getEvent()->getCustomer();

        // At customer account update (in adminhtml), if the account is active
        // but the email has not been sent: send it to the customer to notice it
        if ($this->helper->isEnabled($customer->getStoreId())
            && $customer->getCustomAttribute(Active::CUSTOMER_ACTIVATION_EMAIL_SENT)->getValue() !== '1'
            && $this->activeAttribute->isCustomerActive($customer)
        ) {
            $this->manageUserActivationEmail($customer);
        }
    }

    protected function manageUserActivationEmail(CustomerInterface $customer)
    {
        $this->connection->beginTransaction();
        $blnStatus = true;

        try {
            $this->updateUser($customer);
            $this->sendEmail($customer);
        } catch (CouldNotSaveException $ex) {
            $this->messageManager->addErrorMessage(__('Could not update user, email has not been sent'));
            $blnStatus = false;
        } catch (MailException $e) {
            $this->messageManager->addErrorMessage(
                __('Could not send the email. Please try to desactivate then reactive the user again')
            );
            $blnStatus = false;
        }

        if ($blnStatus) {
            $this->connection->commit();
        } else {
            $this->connection->rollBack();
        }
    }

    /**
     * @param CustomerInterface $customer
     * @throws CouldNotSaveException
     */
    protected function updateUser(CustomerInterface $customer)
    {
        try {
            $updatedCustomer = $this->customerRepository->getById($customer->getId());
            $updatedCustomer->setCustomAttribute(Active::CUSTOMER_ACTIVATION_EMAIL_SENT, true);
            $this->customerRepository->save($updatedCustomer);
        } catch (Exception $ex) {
            $e = new CouldNotSaveException(__($ex->getMessage()), $ex);
            $this->logger->error(__FILE__ . ': ' . $ex->getMessage());
            $this->logger->error(__FILE__ . ': ' . $ex->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @param CustomerInterface $customer
     * @throws MailException
     */
    protected function sendEmail(CustomerInterface $customer)
    {
        try {
            $this->activationEmail->send($customer);
        } catch (Exception $ex) {
            $e = new MailException(__($ex->getMessage()), $ex);
            $this->logger->error(__FILE__ . ': ' . $ex->getMessage());
            $this->logger->error(__FILE__ . ': ' . $ex->getTraceAsString());
            throw  $e;
        }
    }
}
