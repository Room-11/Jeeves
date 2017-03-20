<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Amp\Success;
use Psr\Log\LoggerInterface;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Chat\RoomStatusManager;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Event\MessageEvent;
use function Amp\wait;

class BuiltInActionManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testRegisterLogs()
    {
        $info1 = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $info2 = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $command = $this->getMockBuilder(BuiltInCommand::class)
            ->getMock();

        $info1
            ->method('getCommand')
            ->will($this->returnValue('foo'));

        $info2
            ->method('getCommand')
            ->will($this->returnValue('bar'));

        $command
            ->expects($this->once())
            ->method('getCommandInfo')
            ->will($this->returnValue([$info1, $info2]));

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Registered command name \'foo\' with built in command ' . get_class($command)],
                ['Registered command name \'bar\' with built in command ' . get_class($command)]
            )
        ;


        $builtInCommandManager = new BuiltInActionManager(
            $this->getMockBuilder(BanStorage::class)
                ->getMock(),
            $this->getMockBuilder(RoomStatusManager::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $logger
        );

        $this->assertSame($builtInCommandManager, $builtInCommandManager->registerCommand($command));
    }

    public function testHasRegisteredCommand()
    {
        $builtInCommandManager = new BuiltInActionManager(
            $this->getMockBuilder(BanStorage::class)
                ->getMock(),
            $this->getMockBuilder(RoomStatusManager::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(LoggerInterface::class)
                ->getMock()
        );

        $info = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $command = $this->getMockBuilder(BuiltInCommand::class)
            ->getMock();

        $info
            ->method('getCommand')
            ->will($this->returnValue('foo'));

        $command
            ->expects($this->once())
            ->method('getCommandInfo')
            ->will($this->returnValue([$info]));

        $builtInCommandManager->registerCommand($command);

        $this->assertSame(true, $builtInCommandManager->hasRegisteredCommand('foo'));
        $this->assertSame(false, $builtInCommandManager->hasRegisteredCommand('bar'));
    }

    public function testGetRegisteredCommands()
    {
        $builtInCommandManager = new BuiltInActionManager(
            $this->getMockBuilder(BanStorage::class)
                ->getMock(),
            $this->getMockBuilder(RoomStatusManager::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(LoggerInterface::class)
                ->getMock()
        );

        $info = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $command = $this->getMockBuilder(BuiltInCommand::class)
            ->getMock();

        $info
            ->method('getCommand')
            ->will($this->returnValue('foo'));

        $command
            ->expects($this->once())
            ->method('getCommandInfo')
            ->will($this->returnValue([$info]));

        $builtInCommandManager->registerCommand($command);

        $this->assertSame(['foo' => $info], $builtInCommandManager->getRegisteredCommandInfo());
    }

    public function testHandleCommandDoesntMatch()
    {
        $builtInCommandManager = new BuiltInActionManager(
            $this->getMockBuilder(BanStorage::class)
                ->getMock(),
            $this->getMockBuilder(RoomStatusManager::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(LoggerInterface::class)
                ->getMock()
        );

        $command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('foo'))
        ;

        $this->assertNull(wait($builtInCommandManager->handleCommand($command)));
    }

    public function testHandleCommandWhenBanned()
    {
        $info = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $command = $this->getMockBuilder(BuiltInCommand::class)
            ->getMock();

        $info
            ->method('getCommand')
            ->will($this->returnValue('foo'));

        $command
            ->expects($this->once())
            ->method('getCommandInfo')
            ->will($this->returnValue([$info]));

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Registered command name \'foo\' with built in command ' . get_class($command)],
                ['User #14 is banned, ignoring event #721 for built in commands']
            )
        ;

        $banStorage = $this->getMockBuilder(BanStorage::class)
            ->getMock();

        $roomStorage = $this->getMockBuilder(RoomStatusManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $banStorage
            ->expects($this->once())
            ->method('isBanned')
            ->willReturn(new Success(true))
        ;

        $builtInCommandManager = new BuiltInActionManager($banStorage, $roomStorage, $logger);

        $builtInCommandManager->registerCommand($command);

        $event = $this->getMockBuilder(MessageEvent::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $event
            ->expects($this->once())
            ->method('getId')
            ->willReturn(721)
        ;

        $userCommand = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $userCommand
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('foo'))
        ;

        $userCommand
            ->expects($this->once())
            ->method('getEvent')
            ->willReturn($event)
        ;

        $userCommand
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(14)
        ;

        $this->assertNull(wait($builtInCommandManager->handleCommand($userCommand)));
    }

    public function testHandleCommandWhenMatches()
    {
        $event = $this->getMockBuilder(MessageEvent::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $event
            ->expects($this->once())
            ->method('getId')
            ->willReturn(721)
        ;

        $userCommand = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $userCommand
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('foo'))
        ;

        $userCommand
            ->expects($this->once())
            ->method('getEvent')
            ->willReturn($event)
        ;

        $userCommand
            ->expects($this->once())
            ->method('getUserId')
            ->willReturn(14)
        ;

        $info = $this->getMockBuilder(BuiltInCommandInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $registeredCommand = $this->getMockBuilder(BuiltInCommand::class)
            ->getMock();

        $info
            ->method('getCommand')
            ->will($this->returnValue('foo'));

        $registeredCommand
            ->expects($this->once())
            ->method('getCommandInfo')
            ->will($this->returnValue([$info]));

        $registeredCommand
            ->expects($this->once())
            ->method('handleCommand')
            ->with($this->isInstanceOf($userCommand))
            ->willReturn(new Success())
        ;

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Registered command name \'foo\' with built in command ' . get_class($registeredCommand)],
                ['Passing event #721 to built in command handler ' . get_class($registeredCommand)]
            )
        ;

        $banStorage = $this->getMockBuilder(BanStorage::class)
            ->getMock();

        $roomStorage = $this->getMockBuilder(RoomStatusManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $banStorage
            ->expects($this->once())
            ->method('isBanned')
            ->willReturn(new Success(false))
        ;

        $builtInCommandManager = new BuiltInActionManager($banStorage, $roomStorage, $logger);

        $builtInCommandManager->registerCommand($registeredCommand);

        $this->assertNull(wait($builtInCommandManager->handleCommand($userCommand)));
    }
}
