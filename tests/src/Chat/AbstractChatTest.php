<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\Chat\Client\ChatClient;

abstract class AbstractChatTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ChatClient|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->client = $this->getMockBuilder(ChatClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
