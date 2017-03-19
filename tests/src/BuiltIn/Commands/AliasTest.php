<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Alias;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Room\Room;

class AliasTest extends AbstractCommandTest
{
    /** @var CommandAliasStorage|\PHPUnit_Framework_MockObject_MockObject */
    private $aliasStorage;

    /** @var AdminStorage|\PHPUnit_Framework_MockObject_MockObject */
    private $adminStorage;

    /** @var Alias|\PHPUnit_Framework_MockObject_MockObject */
    private $builtIn;

    /** @var BuiltInActionManager|\PHPUnit_Framework_MockObject_MockObject */
    private $builtInCommandManager;

    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $client;

    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    private $command;

    /** @var PluginManager|\PHPUnit_Framework_MockObject_MockObject */
    private $pluginManager;

    /** @var Room|\PHPUnit_Framework_MockObject_MockObject */
    private $room;

    public function setUp()
    {
        parent::setUp();

        $this->aliasStorage = $this->createMock(CommandAliasStorage::class);
        $this->adminStorage = $this->createMock(AdminStorage::class);
        $this->builtInCommandManager = $this->createMock(BuiltInActionManager::class);
        $this->client = $this->createMock(ChatClient::class);
        $this->pluginManager = $this->createMock(PluginManager::class);

        $this->builtIn = new Alias(
            $this->client,
            $this->aliasStorage,
            $this->adminStorage,
            $this->builtInCommandManager,
            $this->pluginManager
        );

        $this->command = $this->createMock(Command::class);
        $this->room = $this->createMock(Room::class);

        $this->setReturnValue($this->command, 'getUserId', 123);
        $this->setReturnValue($this->command, 'getRoom', $this->room);
    }

    public function testUnAliasCommand()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'unalias');
        $this->setCommandParameter('test');
        $this->setAliasStorageExists('test', new Success(true));

        $this->aliasStorage
            ->expects($this->once())
            ->method('remove')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo('test')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("Alias '!!test' removed");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testUnAliasOnUnknownCommand()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'unalias');
        $this->setCommandParameter('test');
        $this->setAliasStorageExists('test', new Success(false));
        $this->expectMessage("Alias '!!test' is not currently mapped");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testAliasCommand()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'alias');
        $this->setMessageText(new Success('!!alias uptime test'));
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setAliasStorageExists('uptime', new Success(false));

        $this->aliasStorage
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo('uptime'),
                $this->identicalTo('test')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("Command '!!uptime' aliased to '!!test'");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testAliasCommandOnExisting()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'alias');
        $this->setMessageText(new Success('!!alias uptime test'));
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(false);
        $this->setAliasStorageExists('uptime', new Success(true));
        $this->expectReply("Alias '!!uptime' already exists.");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testAliasCommandOnMapped()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'alias');
        $this->setMessageText(new Success('!!alias uptime test'));
        $this->setHasRegisteredCommand(false);
        $this->setIsCommandMappedForRoom(true);
        $this->expectReply(
            "Command 'uptime' is already mapped. Use `!!command list` to display the currently mapped commands."
        );

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testAliasCommandOnBuiltIn()
    {
        $this->setAdmin(true);
        $this->setReturnValue($this->command, 'getCommandName', 'alias');
        $this->setMessageText(new Success('!!alias uptime test'));
        $this->setHasRegisteredCommand(true);
        $this->expectReply("Command 'uptime' is built in and cannot be altered");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandWithoutAdmin()
    {
        $this->setAdmin(false);
        $this->expectReply("I'm sorry Dave, I'm afraid I can't do that");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
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

    private function setMessageText($value)
    {
        $this->client
            ->method('getMessageText')
            ->with($this->identicalTo($this->command->getRoom()), $this->identicalTo($this->command->getId()))
            ->will($this->returnValue($value))
        ;
    }

    private function setHasRegisteredCommand(bool $value)
    {
        $this->builtInCommandManager
            ->method('hasRegisteredCommand')
            ->with($this->identicalTo('uptime'))
            ->will($this->returnValue($value))
        ;
    }

    private function setIsCommandMappedForRoom(bool $value)
    {
        $this->pluginManager
            ->method('isCommandMappedForRoom')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo('uptime')
            )
            ->will($this->returnValue($value))
        ;
    }

    private function setAliasStorageExists(string $aliasCommand, $value)
    {
        $this->aliasStorage
            ->method('exists')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo($aliasCommand)
            )
            ->will($this->returnValue($value))
        ;
    }

    private function setCommandParameter($value)
    {
        $this->command
            ->method('getParameter')
            ->with($this->identicalTo(0))
            ->will($this->returnValue($value))
        ;
    }
}
