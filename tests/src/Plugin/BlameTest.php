<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Success;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Plugins\Blame;
use Room11\StackChat\Client\ChatClient;

class BlameTest extends AbstractPluginTest
{
    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $chatClient;

    protected function setUp()
    {
        parent::setUp();

        $this->chatClient = $this->createMock(ChatClient::class);
        $this->plugin     = new Blame($this->chatClient);
    }

    public function testCommandName()
    {
        $this->assertSame('blame', $this->plugin->getName());
        $this->assertSame(
            'Blames somebody or something',
            $this->plugin->getDescription()
        );
    }

    public function testBlameWithoutParameters()
    {
        /** @var Blame $plugin */
        $plugin = $this->plugin;

        $command = $this->createMock(Command::class);

        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->willReturn(false)
        ;

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with($this->equalTo($command), $this->equalTo('https://img.shields.io/badge/peehaas-FAULT-red.svg'))
            ->willReturn(new Success())
        ;

        $this->assertInstanceOf(Success::class, $plugin->blame($command));
    }

    public function testBlameWithDashes()
    {
        /** @var Blame $plugin */
        $plugin = $this->plugin;

        $command = $this->createMock(Command::class);

        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->willReturn(true)
        ;

        $command
            ->expects($this->once())
            ->method('getParameters')
            ->willReturn(['foo-bar'])
        ;

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with($this->equalTo($command), $this->equalTo('https://img.shields.io/badge/foo_bars-FAULT-red.svg'))
            ->willReturn(new Success())
        ;

        $this->assertInstanceOf(Success::class, $plugin->blame($command));
    }

    public function testBlameAlreadyPossessive()
    {
        /** @var Blame $plugin */
        $plugin = $this->plugin;

        $command = $this->createMock(Command::class);

        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->willReturn(true)
        ;

        $command
            ->expects($this->once())
            ->method('getParameters')
            ->willReturn(['foobars'])
        ;

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with($this->equalTo($command), $this->equalTo('https://img.shields.io/badge/foobars-FAULT-red.svg'))
            ->willReturn(new Success())
        ;

        $this->assertInstanceOf(Success::class, $plugin->blame($command));
    }
}
