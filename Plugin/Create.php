<?php
/**
 * This plugin disconnect the user after account
 * creation if customer account activation by
 * admin is required.
 *
 * @author Eric COURTIAL <e.courtial30@gmail.com>
 * Date: 01/08/2017
 * Time: 11:33
 */
namespace IMI\Magento2CustomerActivation\Plugin;

use IMI\Magento2CustomerActivation\Helper\Data;
use Magento\Customer\Controller\Account\CreatePost;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Customer\Model\Session;

class Create
{
    protected RedirectFactory $resultRedirectFactory;

    protected RedirectInterface $redirect;

    protected Session $customerSession;

    protected Data $helper;

    public function __construct(
        RedirectFactory $redirectFactory,
        RedirectInterface $redirectInterface,
        Session $customerSession,
        Data $helper
    ) {
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirectInterface;
        $this->customerSession = $customerSession;
        $this->helper = $helper;
    }

    /**
     * @param CreatePost $subject
     * @param $result
     * @return Redirect
     */
    public function afterExecute(CreatePost $subject, $result)
    {
        if ($this->helper->isEnabled() && $this->customerSession->getRegisterSuccess()) {
            $lastCustomerId = $this->customerSession->getCustomerId();
            $this->customerSession
                ->logout()
                ->setBeforeAuthUrl($this->redirect->getRefererUrl())
                ->setLastCustomerId($lastCustomerId);

            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            $result = $resultRedirect;
        }

        return $result;
    }
}
