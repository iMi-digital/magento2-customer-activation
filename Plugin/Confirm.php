<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 05/12/2017
 *
 * Plugin to handle email address confirmation (if required)
 */
namespace Enrico69\Magento2CustomerActivation\Plugin;

use Enrico69\Magento2CustomerActivation\Helper\Data;
use Magento\Customer\Controller\Account\Confirm as TargetClass;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Enrico69\Magento2CustomerActivation\Model\AdminNotification;
use Enrico69\Magento2CustomerActivation\Model\Attribute\Active;

class Confirm
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var RedirectInterface
     */
    protected $redirect;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Session
     */
    protected $customerSession;

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
     * @var Active
     */
    protected $activeAttribute;

    protected Data $helper;

    public function __construct(
        RedirectFactory $redirectFactory,
        RedirectInterface $redirectInterface,
        LoggerInterface $logger,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        AdminNotification $adminNotification,
        Active $activeAttribute,
        Data $helper
    ) {
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirectInterface;
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->adminNotification = $adminNotification;
        $this->activeAttribute = $activeAttribute;
        $this->helper = $helper;
    }

    /**
     * @param TargetClass $subject
     * @param $result
     * @return Redirect
     * @throws LocalizedException
     * @throws MailException
     */
    public function afterExecute(TargetClass $subject, $result)
    {
        if ($this->helper->isEnabled() && $this->customerSession->isLoggedIn()
        ) {
            try {
                $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());

                if (!$this->activeAttribute->isCustomerActive($customer)) {
                    $lastCustomerId = $this->customerSession->getCustomerId();
                    $this->customerSession->logout()->setBeforeAuthUrl($this->redirect->getRefererUrl())
                        ->setLastCustomerId($lastCustomerId);

                    /** @var Redirect $resultRedirect */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('*/*/logoutSuccess');
                    $result = $resultRedirect;

                    $this->adminNotification->send($customer);
                }
            } catch (NoSuchEntityException $ex) {
                // If the customer doesn't exists, let the controller to handle it
                unset($ex);
            }
        }

        return $result;
    }
}
