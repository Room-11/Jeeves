<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Plugins\CanIUse;
use Room11\Jeeves\System\PluginCommandEndpoint;

class CanIUseTest extends AbstractPluginTest
{
    public function testCommandName()
    {
        $this->assertSame('CanIUse', $this->plugin->getName());
        $this->assertSame(
            'A quick search tool for CanIUse, a browser comparability feature list for modern standards.',
            $this->plugin->getDescription()
        );
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertSame(null, $this->plugin->getMessageHandler());
    }

    public function testValidCommandEndpoints()
    {
        $result = $this->plugin->getCommandEndpoints();

        $this->assertContainsOnlyInstancesOf(
            PluginCommandEndpoint::class,
            $result,
            'Command endpoints array doesn\'t contain only valid endpoint definitions.'
        );

        $this->assertCount(1, $result, 'Command endpoints array doesn\'t contain exactly 1 endpoint definition.');
    }

    public function testGetLinkCommandWithSearchParameters()
    {
        /** @var CanIUse $plugin */
        $plugin = $this->plugin;
        $client = clone $this->client;

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
                $this->equalTo('[Can I Use Search: `flexbox css3`](http://caniuse.com/flexbox%20css3)')
            );

        $plugin->getLink($command);
    }

    public function testGetLinkCommandWithoutSearchParameters()
    {
        /** @var CanIUse $plugin */
        $plugin = $this->plugin;
        $client = clone $this->client;

        $command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();
        $command
            ->expects($this->once())
            ->method('getParameters')
            ->will($this->returnValue([]));

        $client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($command),
                $this->equalTo('[Can I Use - Support tables for HTML5, CSS3, etc](http://caniuse.com)')
            );

        $plugin->getLink($command);
    }

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->plugin = new CanIUse($this->client);
    }
}
