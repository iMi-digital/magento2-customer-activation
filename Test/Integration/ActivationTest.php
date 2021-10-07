<?php

namespace IMI\Magento2CustomerActivation\Test\Integration;

use IMI\Magento2CustomerActivation\Model\Attribute\Active;
use Laminas\Mail\Header\HeaderWrap;
use Laminas\Mime\Part;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Mail\Message;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\TestFramework\Request;
use Magento\TestFramework\TestCase\AbstractController;
use Magento\Theme\Controller\Result\MessagePlugin;

class ActivationTest extends AbstractController
{
    /**
     * @var TransportBuilderMock
     */
    private $transportBuilderMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->transportBuilderMock = $this->_objectManager->get(TransportBuilderMock::class);
    }

    private function registerCustomer()
    {
        $postData = [
            'prefix' => 'Herr',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'johndoe@example.com',
            'comment' => 'Dummy Comment',
            'password' => 'Dev123456',
            'password_confirmation' => 'Dev123456',
        ];
        $this->getRequest()->setMethod(Request::METHOD_POST);
        $this->getRequest()->setPostValue($postData);

        $this->dispatch('customer/account/createpost');
    }

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 0
     */
    public function testShouldNotDoAnythingWhenDisabled()
    {
        $this->registerCustomer();

        $this->assertRedirect($this->stringContains('customer/account/index'));
    }

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     */
    public function testShouldNotActivateCustomerAfterRegistration()
    {
        $this->registerCustomer();

        $this->assertRedirect($this->stringContains('customer/account/login'));
        $this->assertSessionMessages($this->equalTo([(string)__('We will enable your account soon.')]),
            MessageInterface::TYPE_NOTICE);
    }

    private function loginCustomer(bool $active)
    {
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->_objectManager->create(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById(1);
        $customer->setCustomAttribute(Active::CUSTOMER_ACCOUNT_ACTIVE, $active);
        $customerRepository->save($customer);

        $this->getRequest()->setMethod(Request::METHOD_POST);
        $this->getRequest()->setPostValue([
            'login' => [
                'username' => 'customer@example.com',
                'password' => 'password',
            ],
        ]);

        $this->dispatch('customer/account/loginPost');
    }

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testShouldPreventLoginForNonActiveCustomers()
    {
        /** @var Session $session */
        $session = $this->_objectManager->get(Session::class);

        $this->loginCustomer(false);

        $this->assertRedirect($this->stringContains('customer/account/login'));
        $this->assertFalse($session->isLoggedIn());
        $this->assertSessionMessages($this->equalTo([(string)__('Your account has not been enabled yet.')]),
            MessageInterface::TYPE_NOTICE);
    }

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testShouldAllowLoginForActiveCustomers()
    {
        /** @var Session $session */
        $session = $this->_objectManager->get(Session::class);

        $this->loginCustomer(true);

        $this->assertRedirect($this->stringContains('customer/account/'));
        $this->assertTrue($session->isLoggedIn());
        $this->assertEmpty($this->getMessages());
    }
}
