<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\Chat\Client\ChatClient;

abstract class ChatTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ChatClient
     */
    protected $client;

    protected function setUp()
    {
        parent::setUp();

        $this->client = $this->getMockBuilder(ChatClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
