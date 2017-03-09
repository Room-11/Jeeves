<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\BuiltIn;

use Room11\Jeeves\Chat\Client\ChatClient;

abstract class AbstractBuiltInTest extends \PHPUnit\Framework\TestCase
{
    protected $client;

    protected function setUp()
    {
        parent::setUp();

        $this->client = $this->getMockBuilder(ChatClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
