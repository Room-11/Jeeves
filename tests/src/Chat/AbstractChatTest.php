<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\StackChat\Client\Client;

abstract class AbstractChatTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Client|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
