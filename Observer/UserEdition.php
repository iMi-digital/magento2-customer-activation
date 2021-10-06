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
use IMI\Magento2CustomerActivation\Setup\InstallData;
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
     * @var ActivationEmail
     */
    protected $activationEmail;

    /**
     * @var AdapterInterface
     */
    protected $connexion;

    /**
     * @var Active
     */
    protected $activeAttribute;

    /** @var Data */
    protected $helper;

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
        $this->connexion = $resourceConnection->getConnection();
        $this->activeAttribute = $activeAttribute;
        $this->helper = $helper;
    }

    /**
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        /** @var CustomerInterface $customer */

        // At customer account update (in adminhtml), if the account is active
        // but the email has not been sent: send it to the customer to notice it
        if ($this->helper->isEnabled($customer->getStoreId())
            && $customer->getCustomAttribute(Active::CUSTOMER_ACTIVATION_EMAIL_SENT)->getValue() !== '1'
            && $this->activeAttribute->isCustomerActive($customer)
        ) {
            $this->manageUserActivationEmail($customer);
        }
    }

    /**
     * @param CustomerInterface $customer
     */
    protected function manageUserActivationEmail($customer)
    {
        $this->connexion->beginTransaction();
        $blnStatus = true;

        try {
            $this->updateUser($customer);
            $this->sendEmail($customer);
        } catch (CouldNotSaveException $ex) {
            $this->messageManager->addErrorMessage("Impossible to update user, email has not been sent");
            $blnStatus = false;
        } catch (MailException $e) {
            $this->messageManager->addErrorMessage(
                "Impossible to send the email. Please try to desactivate then reactive the user again"
            );
            $blnStatus = false;
        }

        if ($blnStatus) {
            $this->connexion->commit();
        } else {
            $this->connexion->rollBack();
        }
    }

    /**
     * @param CustomerInterface $customer
     * @throws CouldNotSaveException
     */
    protected function updateUser($customer)
    {
        try {
            $updatedCustomer = $this->customerRepository->getById($customer->getId());
            $updatedCustomer->setCustomAttribute(Active::CUSTOMER_ACTIVATION_EMAIL_SENT, true);
            $this->customerRepository->save($updatedCustomer);
        } catch (Exception $ex) {
            $e = new CouldNotSaveException(__($ex->getMessage()), $ex);
            $this->logger->error(__FILE__ . ' : ' . $ex->getMessage());
            $this->logger->error(__FILE__ . ' : ' . $ex->getTraceAsString());
            throw  $e;
        }
    }

    /**
     * @param CustomerInterface $customer
     * @throws MailException
     */
    protected function sendEmail($customer)
    {
        try {
            $this->activationEmail->send($customer);
        } catch (Exception $ex) {
            $e = new MailException(__($ex->getMessage()), $ex);
            $this->logger->error(__FILE__ . ' : ' . $ex->getMessage());
            $this->logger->error(__FILE__ . ' : ' . $ex->getTraceAsString());
            throw  $e;
        }
    }
}
