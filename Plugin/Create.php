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
namespace Enrico69\Magento2CustomerActivation\Plugin;

use Enrico69\Magento2CustomerActivation\Helper\Data;
use Magento\Customer\Controller\Account\CreatePost;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\Response\RedirectInterface;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Session;

class Create
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

    protected Data $helper;

    public function __construct(
        RedirectFactory $redirectFactory,
        RedirectInterface $redirectInterface,
        LoggerInterface $logger,
        Session $customerSession,
        Data $helper
    ) {
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirectInterface;
        $this->logger = $logger;
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
            $this->customerSession->logout()->setBeforeAuthUrl($this->redirect->getRefererUrl())
                ->setLastCustomerId($lastCustomerId);

            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('*/*/logoutSuccess');
            $result = $resultRedirect;
        }

        return $result;
    }
}
