<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Command;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Plugins\Chuck;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;

class CommandTest extends AbstractCommandTest
{
    private $aliasStorage;
    private $adminStorage;
    private $builtIn;
    private $builtInCommandManager;
    private $client;
    private $command;
    private $pluginManager;
    private $room;

    public function setUp()
    {
        parent::setUp();

        $this->aliasStorage = $this->createMock(CommandAliasStorage::class);
        $this->adminStorage = $this->createMock(AdminStorage::class);
        $this->builtInCommandManager = $this->createMock(BuiltInActionManager::class);
        $this->client = $this->createMock(ChatClient::class);
        $this->command = $this->createMock(CommandMessage::class);
        $this->pluginManager = $this->createMock(PluginManager::class);

        $this->builtIn = new Command(
            $this->pluginManager,
            $this->builtInCommandManager,
            $this->client,
            $this->adminStorage,
            $this->aliasStorage
        );

        $this->room = $this->createMock(Room::class);

        $this->setReturnValue($this->command, 'getUserId', 123);
        $this->setReturnValue($this->command, 'getRoom', $this->room);
    }

    // TODO - Test remap after refactor.

    public function testCommandUnmap()
    {
        $this->setCommandParameters([[0, 'unmap'], [1, 'test']]);
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(true);

        $this->pluginManager
            ->expects($this->once())
            ->method('unmapCommandForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo('test')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->room),
                $this->equalTo(sprintf(
                    $this->builtIn::RESPONSE_MESSAGES['command_unmap_success'],
                    'test'
                ))
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandUnmapOnUnMapped()
    {
        $this->setCommandParameters([[0, 'unmap'], [1, 'test']]);
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_not_mapped'], 'test'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandUnmapOnBuiltIn()
    {
        $this->setCommandParameters([[0, 'unmap'], [1, 'uptime']]);
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(true);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_built_in'], 'uptime'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandUnmapWithoutParameters()
    {
        $this->setCommandParameter('map');
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['syntax']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandUnmapWithoutAdmin()
    {
        $this->setCommandParameter('unmap');
        $this->setAdmin(false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['user_not_admin']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMap()
    {
        $this->setCommandParameters([
            [0, 'map'], [1, 'chucky'], [2, 'chuck'], [3, 'chuckEndpoint']
        ]);

        $this->fullMapTest();

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapWithoutEndpoint()
    {
        $this->setCommandParameters([
            [0, 'map'], [1, 'chucky'], [2, 'chuck']
        ]);

        $this->fullMapTest();

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapInvalidEndpoint()
    {
        $this->setCommandParameters([
            [0, 'map'], [1, 'chucky'], [2, 'chuck'], [3, 'chucker']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setReturnValue($this->pluginManager, 'isPluginRegistered', true);
        $this->setReturnValue($this->pluginManager, 'isPluginEnabledForRoom', true);

        $this->setReturnValue($this->pluginManager, 'getPluginCommandEndpoints', [
            'firstEndpoint' => [],
            'secondEndpoint' => []
        ]);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['unknown_endpoint'],
            'chucker', 'chuck'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapMultipleEndpoints()
    {
        $this->setCommandParameters([
            [0, 'map'], [1, 'chucky'], [2, 'chuck']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setReturnValue($this->pluginManager, 'isPluginRegistered', true);
        $this->setReturnValue($this->pluginManager, 'isPluginEnabledForRoom', true);

        $this->setReturnValue($this->pluginManager, 'getPluginCommandEndpoints', [
            'firstEndpoint' => [],
            'secondEndpoint' => []
        ]);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['multiple_endpoints'],
            'chuck', 2
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapOnDisabledPlugin()
    {
        $this->setCommandParameter('map');
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setReturnValue($this->pluginManager, 'isPluginRegistered', true);
        $this->setReturnValue($this->pluginManager, 'isPluginEnabledForRoom', false);

        $plugin = $this->createMock(Chuck::class);
        $this->setReturnValue($plugin, 'getName', 'Chuck');
        $this->setReturnValue($this->pluginManager, 'getPluginByName', $plugin);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['plugin_not_enabled'], 'Chuck'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapOnUnknownPlugin()
    {
        $this->setCommandParameters([
            [0, 'map'], [1, 'gh'], [2, 'githubb']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setReturnValue($this->pluginManager, 'isPluginRegistered', false);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['unknown_plugin'], 'githubb'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapOnMapped()
    {
        $this->setCommandParameters([[0, 'map'], [1, 'test']]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(true);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_already_mapped'], 'test'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapOnBuiltIn()
    {
        $this->setCommandParameters([[0, 'map'], [1, 'uptime']]);
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(true);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_built_in'], 'uptime'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapWithoutParameters()
    {
        $this->setCommandParameter('map');
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['syntax']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandMapWithoutAdmin()
    {
        $this->setCommandParameter('map');
        $this->setAdmin(false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['user_not_admin']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandClone()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'vamp'], [2, 'lmgtfy']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);

        $this->setIsCommandMappedForRooms([
            [$this->room, 'vamp', false], [$this->room, 'lmgtfy', true]
        ]);

        $this->setReturnValue($this->pluginManager, 'getMappedCommandsForRoom', [
            'lmgtfy' => [
                'plugin_name' => 'lmgtfy',
                'endpoint_name' => 'lmgtfy_endpoint'
            ]
        ]);

        $this->setReturnValue($this->pluginManager, 'isPluginEnabledForRoom', true);

        $this->pluginManager
            ->expects($this->once())
            ->method('mapCommandForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo('lmgtfy'),
                $this->identicalTo('lmgtfy_endpoint'),
                $this->identicalTo('vamp')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_map_success'],
            'vamp', 'lmgtfy', 'lmgtfy_endpoint'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneOnDisabledPlugin()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'vamp'], [2, 'lmgtfy']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);

        $this->setIsCommandMappedForRooms([
            [$this->room, 'vamp', false], [$this->room, 'lmgtfy', true]
        ]);

        $this->setReturnValue($this->pluginManager, 'getMappedCommandsForRoom', [
            'lmgtfy' => [
                'plugin_name' => 'lmgtfy'
            ]
        ]);

        $this->pluginManager
            ->method('isPluginEnabledForRoom')
            ->with(
                $this->identicalTo('lmgtfy'),
                $this->identicalTo($this->room)
            )
            ->will($this->returnValue(false))
        ;

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['plugin_not_enabled'],
            'lmgtfy'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneOnUnMappedOldCommand()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'testing'], [2, 'test']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_not_mapped'],
            'test'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneOnBuiltInOldCommand()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'test'], [2, 'command']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);

        $this->setHasRegisteredCommands([
            ['test', false], ['command', true]
        ]);

        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_built_in'],
            'command'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneOnMappedNewCommand()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'testing'], [2, 'test']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(true);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_already_mapped'],
            'testing'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneOnBuiltInNewCommand()
    {
        $this->setCommandParameters([
            [0, 'clone'], [1, 'uptime'], [2, 'testing']
        ]);

        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(true);
        $this->expectReply(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_built_in'],
            'uptime'
        ));

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneWithoutParameters()
    {
        $this->setCommandParameter('clone');
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['syntax']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandCloneWithouAdmin()
    {
        $this->setCommandParameter('clone');
        $this->setAdmin(false);
        $this->expectReply($this->builtIn::RESPONSE_MESSAGES['user_not_admin']);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandList()
    {
        $this->setCommandParameter('list');
        $this->assertList();

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandHelp()
    {
        $this->setCommandParameter('help');
        $this->expectMessage($this->builtIn::COMMAND_HELP_TEXT);

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testHelpCommand()
    {
        $this->setReturnValue($this->command, 'getCommandName', 'help');
        $this->assertList();

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    private function fullMapTest()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setReturnValue($this->pluginManager, 'isPluginRegistered', true);
        $this->setReturnValue($this->pluginManager, 'isPluginEnabledForRoom', true);
        $plugin = $this->createMock(Chuck::class);
        $this->setReturnValue($this->pluginManager, 'getPluginByName', $plugin);
        $this->setReturnValue($plugin, 'getName', 'chuck');

        $this->setReturnValue($this->pluginManager, 'getPluginCommandEndpoints', [
            'chuckEndpoint' => []
        ]);

        $this->pluginManager
            ->expects($this->once())
            ->method('mapCommandForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo($plugin),
                $this->identicalTo('chuckEndpoint'),
                $this->identicalTo('chucky')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage(sprintf(
            $this->builtIn::RESPONSE_MESSAGES['command_map_success'],
            'chucky', 'chuck', 'chuckEndpoint'
        ));
    }

    private function assertList()
    {
        $this->aliasStorage
            ->method('getAll')
            ->will($this->returnValue(new Success([])))
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
    }

    private function setCommandParameter($value)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValue($value))
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

    private function setCommandParameters(array $parameters)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValueMap($parameters))
        ;
    }

    private function setHasRegisteredCommand(bool $value)
    {
        $this->builtInCommandManager
            ->method('hasRegisteredCommand')
            ->with($this->isType('string'))
            ->will($this->returnValue($value))
        ;
    }

    private function setHasRegisteredCommands(array $values)
    {
        $this->builtInCommandManager
            ->method('hasRegisteredCommand')
            ->with($this->isType('string'))
            ->will($this->returnValueMap($values))
        ;
    }

    private function setIsCommandMappedForRoom(bool $value)
    {
        $this->pluginManager
            ->method('isCommandMappedForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->isType('string')
            )
            ->will($this->returnValue($value))
        ;
    }

    private function setIsCommandMappedForRooms(array $values)
    {
        $this->pluginManager
            ->method('isCommandMappedForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->isType('string')
            )
            ->will($this->returnValueMap($values))
        ;
    }
}
