<?php

namespace IMI\Magento2CustomerActivation\Test\Integration;

use IMI\Magento2CustomerActivation\Model\Attribute\Active;
use Laminas\Mail\Header\HeaderWrap;
use Laminas\Mime\Part;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
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

    private function dumpResponse()
    {
        print_r("Status Code: {$this->getResponse()->getStatusCode()}\n");
        echo "Headers:\n";
        print_r($this->getRequest()->getHeaders());
        echo "Content:\n";
        print_r($this->getRequest()->getContent());
        echo "Messages:\n";
        print_r($this->getMessages());
    }

    private function registerCustomer()
    {
        $postData = [
            'prefix' => 'Mr.',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'some_new_dev.user@example.com',
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
     * @magentoDbIsolation enabled
     */
    public function testShouldNotDoAnythingWhenDisabled()
    {
        $this->registerCustomer();

        $this->assertRedirect($this->stringContains('customer/account/'));
    }

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     * @magentoDbIsolation enabled
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

    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     * @magentoDataFixture Magento/Customer/_files/customer_confirmation_email_address_with_special_chars.php
     * @magentoDbIsolation enabled
     */
    public function testShouldNotAllowLoginAfterEmailConfirmation()
    {
        $email = 'customer+confirmation@example.com';

        /** @var CustomerRegistry $customerRegistry */
        $customerRegistry = $this->_objectManager->get(CustomerRegistry::class);
        $customerData = $customerRegistry->retrieveByEmail($email);

        // Set account not active
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->_objectManager->create(CustomerRepositoryInterface::class);
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $customerRepository->getById($customerData->getId());
        $customer->setCustomAttribute(Active::CUSTOMER_ACCOUNT_ACTIVE, 0);
        $customerRepository->save($customer);

        // First part is copied from \Magento\Customer\Controller\AccountTest::testConfirmationEmailWithSpecialCharacters
        // COPY START
        $this->dispatch('customer/account/confirmation/email/customer%2Bconfirmation%40email.com');
        $this->getRequest()->setMethod(Request::METHOD_POST);
        $this->getRequest()->setPostValue('email', $email);
        $this->dispatch('customer/account/confirmation/email/customer%2Bconfirmation%40email.com');

        $this->assertRedirect($this->stringContains('customer/account/index'));
        $this->assertSessionMessages(
            $this->equalTo([__('Please check your email for confirmation key.')]),
            MessageInterface::TYPE_SUCCESS
        );

        /** @var $message Message */
        $message = $this->transportBuilderMock->getSentMessage();
        $rawMessage = $message->getRawMessage();

        /** @var Part $messageBodyPart */
        $messageBodyParts = $message->getBody()->getParts();
        $messageBodyPart = reset($messageBodyParts);
        $messageEncoding = $messageBodyPart->getCharset();
        $name = 'John Smith';

        if (strtoupper($messageEncoding) !== 'ASCII') {
            $name = HeaderWrap::mimeEncodeValue($name, $messageEncoding);
        }

        $nameEmail = sprintf('%s <%s>', $name, $email);

        $this->assertStringContainsString('To: ' . $nameEmail, $rawMessage);

        $content = $messageBodyPart->getRawContent();
        $confirmationUrl = $this->getConfirmationUrlFromMessageContent($content);
        $this->getRequest()->setMethod(Request::METHOD_GET);
        $this->getRequest()
            ->setRequestUri($confirmationUrl)
            ->setPathInfo()
            ->setActionName('confirm');
        $cookieManager = $this->_objectManager->get(CookieManagerInterface::class);
        $jsonSerializer = $this->_objectManager->get(Json::class);
        $cookieManager->setPublicCookie(
            MessagePlugin::MESSAGES_COOKIES_NAME,
            $jsonSerializer->serialize([])
        );
        $this->dispatch($confirmationUrl);

        // COPY END

        $this->dumpResponse();

        $this->assertRedirect($this->stringContains('customer/account/login'));
        $this->assertSessionMessages(
            $this->equalTo([__('We will enable your account soon.')]),
            MessageInterface::TYPE_NOTICE
        );
    }

    /**
     * Get confirmation URL from message content.
     *
     * Copied from \Magento\Customer\Controller\AccountTest::getConfirmationUrlFromMessageContent
     *
     * @param string $content
     *
     * @return string
     */
    private function getConfirmationUrlFromMessageContent(string $content): string
    {
        $confirmationUrl = '';

        if (preg_match('<a\s*href="(?<url>.*?)".*>', $content, $matches)) {
            $confirmationUrl = $matches['url'];
            $confirmationUrl = str_replace('http://localhost/index.php/', '', $confirmationUrl);
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $confirmationUrl = html_entity_decode($confirmationUrl);
        }

        return $confirmationUrl;
    }
}
