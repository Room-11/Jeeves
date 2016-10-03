<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Plugins\CanIUse;

class CanIUseTest extends AbstractPluginTest
{
    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->plugin = new CanIUse($this->client);
    }

    public function testCommandName()
    {
        $this->assertSame('CanIUse', $this->plugin->getName());
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertSame(null, $this->plugin->getMessageHandler());
    }

    public function testGetLinkCommand()
    {
        /** @var CanIUse $plugin */
        $plugin = $this->plugin;
        $client = $this->client;

        $command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
        $command
            ->expects($this->once())
            ->method('getParameters')
            ->will($this->returnValue(['flexbox', 'css3']));

        $client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($command),
                $this->equalTo('[Can I Use Search: `flexbox css3`](http://caniuse.com/flexbox+css3)')
            );

        $plugin->getLink($command);
    }
}
