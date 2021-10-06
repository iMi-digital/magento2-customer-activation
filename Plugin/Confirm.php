<?php
/**
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 05/12/2017
 *
 * Plugin to handle email address confirmation (if required)
 */
namespace IMI\Magento2CustomerActivation\Plugin;

use IMI\Magento2CustomerActivation\Helper\Data;
use Magento\Customer\Controller\Account\Confirm as TargetClass;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use IMI\Magento2CustomerActivation\Model\AdminNotification;
use IMI\Magento2CustomerActivation\Model\Attribute\Active;

class Confirm
{
    protected RedirectFactory $resultRedirectFactory;

    protected RedirectInterface $redirect;

    protected Session $customerSession;

    protected CustomerRepositoryInterface $customerRepository;

    protected AdminNotification $adminNotification;

    protected Active $activeAttribute;

    protected Data $helper;

    public function __construct(
        RedirectFactory $redirectFactory,
        RedirectInterface $redirectInterface,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AdminNotification $adminNotification,
        Active $activeAttribute,
        Data $helper
    ) {
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirectInterface;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
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
        if ($this->helper->isEnabled() && $this->customerSession->isLoggedIn()) {
            try {
                $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());

                if (!$this->activeAttribute->isCustomerActive($customer)) {
                    $lastCustomerId = $this->customerSession->getCustomerId();
                    $this->customerSession
                        ->logout()
                        ->setBeforeAuthUrl($this->redirect->getRefererUrl())
                        ->setLastCustomerId($lastCustomerId);

                    /** @var Redirect $resultRedirect */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/login');
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
