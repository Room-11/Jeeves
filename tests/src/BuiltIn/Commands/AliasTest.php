<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Alias;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\PluginManager;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\CommandAlias as CommandAliasStorage;

class AliasTest extends AbstractCommandTest
{
    private $aliasStorage;
    private $adminStorage;
    private $builtInCommandManager;
    private $PluginManager;
    private $builtIn;
    private $client;
    private $command;
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

    public function testAliasCommandOnBuiltIn()
    {
        $this->setAdmin(true);

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
}
