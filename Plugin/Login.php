<?php
/**
 * This plugin disconnect the user after login
 * if its account has not been activated by an admin AND
 * if account activation is required
 *
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 * Time: 11:31
 */
namespace IMI\Magento2CustomerActivation\Plugin;

use IMI\Magento2CustomerActivation\Helper\Data;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use IMI\Magento2CustomerActivation\Model\Attribute\Active;

class Login
{
    protected RedirectFactory $resultRedirectFactory;

    protected RedirectInterface $redirect;

    protected Session $customerSession;

    protected CustomerRepositoryInterface $customerRepository;

    protected ManagerInterface $messageManager;

    protected Active $activeAttribute;

    protected Data $helper;

    public function __construct(
        RedirectFactory $redirectFactory,
        RedirectInterface $redirectInterface,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        Active $activeAttribute,
        Data $helper
    ) {
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirectInterface;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->activeAttribute = $activeAttribute;
        $this->helper = $helper;
    }

    /**
     * @param LoginPost $subject
     * @param $result
     * @return Redirect
     * @throws LocalizedException
     */
    public function afterExecute(LoginPost $subject, $result)
    {
        if ($this->helper->isEnabled()) {
            try {
                $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());

                if (!$this->activeAttribute->isCustomerActive($customer)) {
                    $lastCustomerId = $this->customerSession->getCustomerId();
                    $this->customerSession
                        ->logout()
                        ->setBeforeAuthUrl($this->redirect->getRefererUrl())
                        ->setLastCustomerId($lastCustomerId);

                    $this->messageManager->addNoticeMessage(__('Your account has not been enabled yet.'));

                    /** @var Redirect $resultRedirect */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/login');
                    $result = $resultRedirect;
                }
            } catch (NoSuchEntityException $ex) {
                // If the customer doesn't exists, let the controller to handle it
                unset($ex);
            }
        }

        return $result;
    }
}
