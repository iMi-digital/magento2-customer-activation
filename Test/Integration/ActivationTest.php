<?php

namespace IMI\Magento2CustomerActivation\Test\Integration;

use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Request;
use Magento\TestFramework\TestCase\AbstractController;

class ActivationTest extends AbstractController
{
    /**
     * @magentoConfigFixture current_store customer/create_account/customer_account_activation 1
     */
    public function testShouldNotActivateCustomerAfterRegistration()
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
        $this->assertTrue($this->getResponse()->isRedirect(), 'Response should be redirect');
        $this->assertRedirect($this->stringContains('customer/account/login'));
        $this->assertSessionMessages($this->equalTo([(string)__('We will enable your account soon.')]),
            MessageInterface::TYPE_NOTICE);
    }
}
