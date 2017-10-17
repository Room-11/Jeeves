<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Plugins\Booze;
use Room11\StackChat\Client\Client;

class BoozeTest extends TestCase
{
    private $chatClient;

    private $httpClient;

    private $command;

    public function setUp()
    {
        $this->chatClient = $this->createMock(Client::class);
        $this->httpClient = $this->createMock(HttpClient::class);
        $this->command    = $this->createMock(Command::class);
    }

    public function testGetName()
    {
        $plugin = new Booze($this->chatClient, $this->httpClient);

        $this->assertSame('Booze', $plugin->getName());
    }

    public function testFindBoozeThrowsOnErrorFindingDrink()
    {
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->throwException(new \Exception))
        ;

        $this->chatClient
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->isInstanceOf(Command::class),
                "You've had enough already. Also something went wrong trying to find your drink."
            )
            ->will($this->returnValue(new Success()))
        ;

        $plugin = new Booze($this->chatClient, $this->httpClient);

        \Amp\wait(\Amp\resolve($plugin->findBooze($this->command)));
    }

    public function testFindBoozeNoSpiritsFound()
    {
        $response = $this->createMock(Response::class);

        $response
            ->method('getBody')
            ->will($this->returnValue('{"spirits":[]}'))
        ;

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->returnValue(new Success($response)))
        ;

        $this->chatClient
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->isInstanceOf(Command::class),
                "No results for: foo"
            )
            ->will($this->returnValue(new Success()))
        ;

        $plugin = new Booze($this->chatClient, $this->httpClient);

        $this->command
            ->method('getCommandText')
            ->will($this->returnValue('foo'))
        ;

        \Amp\wait(\Amp\resolve($plugin->findBooze($this->command)));
    }
}
