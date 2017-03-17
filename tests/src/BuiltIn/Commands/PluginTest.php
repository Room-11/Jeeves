<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Plugin;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Plugins\Issue;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;

class PluginTest extends AbstractCommandTest
{
    private const COMMAND_HELP_TEXT =
        "Sub-commands (* indicates admin-only):"
        . "\n"
        . "\n help     - display this message"
        . "\n list     - display a list of plugins, or a list of endpoints for the specified plugin."
        . "\n             Syntax: plugin list [<name>]"
        . "\n *enable  - Enable a plugin in this room."
        . "\n             Syntax: plugin enable <name>"
        . "\n *disable - Disable a plugin in this room."
        . "\n             Syntax: plugin disable <name>"
        . "\n status   - Query whether a plugin is enabled in this room."
        . "\n             Syntax: plugin status <name>"
    ;

    private $adminStorage;
    private $builtIn;
    private $client;
    private $command;
    private $pluginManager;
    private $room;

    public function setUp()
    {
        parent::setUp();

        $this->adminStorage = $this->createMock(AdminStorage::class);
        $this->client = $this->createMock(ChatClient::class);
        $this->command = $this->createMock(CommandMessage::class);
        $this->pluginManager = $this->createMock(PluginManager::class);

        $this->builtIn = new Plugin(
            $this->pluginManager,
            $this->client,
            $this->adminStorage
        );

        $this->room = $this->createMock(Room::class);

        $this->setReturnValue($this->command, 'getUserId', 123);
        $this->setReturnValue($this->command, 'getRoom', $this->room);
    }

    public function testPluginStatusOnDisabled()
    {
        $this->setCommandParameters([[0, 'status'], [1, 'issue']]);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', false);
        $this->expectMessage("Plugin 'issue' is currently disabled in this room");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginStatusOnEnabled()
    {
        $this->setCommandParameters([[0, 'status'], [1, 'issue']]);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', true);
        $this->expectMessage("Plugin 'issue' is currently enabled in this room");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginStatusOnUnregistered()
    {
        $this->setCommandParameters([[0, 'status'], [1, 'issuer']]);
        $this->isPluginRegistered('issuer', false);
        $this->expectReply('Invalid plugin name');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginStatusWithoutParameter()
    {
        $this->setCommandParameters([[0, 'status'], [1, null]]);
        $this->expectReply('No plugin name supplied');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginDisable()
    {
        $this->setCommandParameters([[0, 'disable'], [1, 'issue']]);
        $this->setAdmin(true);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', true);

        $this->pluginManager
            ->expects($this->once())
            ->method('disablePluginForRoom')
            ->with(
                $this->identicalTo('issue'),
                $this->identicalTo($this->room)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("Plugin 'issue' is now disabled in this room");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginDisableOnDisabled()
    {
        $this->setCommandParameters([[0, 'disable'], [1, 'issue']]);
        $this->setAdmin(true);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', false);
        $this->expectReply('Plugin already disabled in this room');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginDisableWithouParameter()
    {
        $this->setCommandParameters([[0, 'disable'], [1, null]]);
        $this->setAdmin(true);
        $this->expectReply('No plugin name supplied');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginDisableWithoutAdmin()
    {
        $this->setCommandParameter('disable');
        $this->setAdmin(false);
        $this->expectReply("I'm sorry Dave, I'm afraid I can't do that");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginEnable()
    {
        $this->setCommandParameters([[0, 'enable'], [1, 'issue']]);
        $this->setAdmin(true);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', false);

        $this->pluginManager
            ->expects($this->once())
            ->method('enablePluginForRoom')
            ->with(
                $this->identicalTo('issue'),
                $this->identicalTo($this->room)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("Plugin 'issue' is now enabled in this room");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginEnableOnEnabled()
    {
        $this->setCommandParameters([[0, 'enable'], [1, 'issue']]);
        $this->setAdmin(true);
        $this->isPluginRegistered('issue', true);
        $this->isPluginEnabledForRoom('issue', true);
        $this->expectReply('Plugin already enabled in this room');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginEnableOnUnregistered()
    {
        $this->setCommandParameters([[0, 'enable'], [1, 'issuer']]);
        $this->setAdmin(true);
        $this->isPluginRegistered('issuer', false);
        $this->expectReply('Invalid plugin name');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginEnableWithoutParameter()
    {
        $this->setCommandParameters([[0, 'enable'], [1, null]]);
        $this->setAdmin(true);
        $this->expectReply('No plugin name supplied');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginEnableWithoutAdmin()
    {
        $this->setCommandParameter('enable');
        $this->setAdmin(false);
        $this->expectReply("I'm sorry Dave, I'm afraid I can't do that");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginListEndpoints()
    {
        $this->setCommandParameters([[0, 'list'], [1, 'issue']]);
        $this->isPluginRegistered('issue', true);
        $plugin = $this->createMock(Issue::class);

        $this->pluginManager
            ->method('getPluginByName')
            ->with($this->identicalTo('issue'))
            ->will($this->returnValue($plugin))
        ;

        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->isType('string')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginListEndpointsOnInvalidPlugin()
    {
        $this->setCommandParameters([[0, 'list'], [1, 'issuer']]);
        $this->isPluginRegistered('issuer', false);
        $this->expectReply('Invalid plugin name');

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginListWithoutParameter()
    {
        $this->setCommandParameters([[0, 'list'], [1, null]]);

        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->isType('string')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testPluginHelp()
    {
        $this->setCommandParameter('help');
        $this->expectMessage(self::COMMAND_HELP_TEXT);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    private function setCommandParameter($value)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValue($value))
        ;
    }

    private function setCommandParameters(array $parameters)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValueMap($parameters))
        ;
    }

    private function expectMessage(string $message)
    {
        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->equalTo($message)
            )
            ->will($this->returnValue(new Success(true)))
        ;
    }

    private function expectReply(string $message)
    {
        $this->client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($this->command),
                $this->identicalTo($message)
            )
            ->will($this->returnValue(new Success(true)))
        ;
    }

    private function isPluginRegistered(string $plugin, bool $value)
    {
        $this->pluginManager
            ->method('isPluginRegistered')
            ->with($this->identicalTo($plugin))
            ->will($this->returnValue($value))
        ;
    }

    private function isPluginEnabledForRoom(string $plugin, bool $value)
    {
        $this->pluginManager
            ->method('isPluginEnabledForRoom')
            ->with($this->identicalTo($plugin))
            ->will($this->returnValue($value))
        ;
    }

    private function setAdmin(bool $isAdmin)
    {
        $this->adminStorage
            ->method('isAdmin')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(123)
            )
            ->will($this->returnValue(new Success($isAdmin)))
        ;
    }
}
