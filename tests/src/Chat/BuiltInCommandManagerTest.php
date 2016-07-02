<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\System\BuiltInCommandManager;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Amp\Success;
use function Amp\wait;


class BuiltInCommandManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testRegisterLogs()
    {
        $command = $this->getMock(BuiltInCommand::class);

        $command
            ->expects($this->once())
            ->method('getCommandNames')
            ->will($this->returnValue(['foo', 'bar']))
        ;

        $logger = $this->getMock(Logger::class);

        $logger
            ->expects($this->exactly(2))
            ->method('log')
            ->withConsecutive(
                [Level::DEBUG, 'Registering command name \'foo\' with built in command ' . get_class($command)],
                [Level::DEBUG, 'Registering command name \'bar\' with built in command ' . get_class($command)]
            )
        ;

        $builtInCommandManager = new BuiltInCommandManager($this->getMock(BanStorage::class), $logger);

        $this->assertSame($builtInCommandManager, $builtInCommandManager->register($command));
    }

    public function testGetRegisteredCommands()
    {
        $builtInCommandManager = new BuiltInCommandManager(
            $this->getMock(BanStorage::class),
            $this->getMock(Logger::class)
        );

        $command = $this->getMock(BuiltInCommand::class);

        $command
            ->expects($this->once())
            ->method('getCommandNames')
            ->will($this->returnValue(['foo', 'bar']))
        ;

        $builtInCommandManager->register($command);

        $this->assertSame(['foo', 'bar'], $builtInCommandManager->getRegisteredCommands());
    }

    public function testHandleCommandDoesntMatch()
    {
        $builtInCommandManager = new BuiltInCommandManager(
            $this->getMock(BanStorage::class),
            $this->getMock(Logger::class)
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
        $registeredCommand = $this->getMock(BuiltInCommand::class);

        $registeredCommand
            ->expects($this->once())
            ->method('getCommandNames')
            ->will($this->returnValue(['foo']))
        ;

        $logger = $this->getMock(Logger::class);

        $logger
            ->expects($this->exactly(2))
            ->method('log')
            ->withConsecutive(
                [Level::DEBUG, 'Registering command name \'foo\' with built in command ' . get_class($registeredCommand)],
                [Level::DEBUG, 'User #14 is banned, ignoring event #721 for built in commands']
            )
        ;

        $banStorage = $this->getMock(BanStorage::class);

        $banStorage
            ->expects($this->once())
            ->method('isBanned')
            ->willReturn(new Success(true))
        ;

        $builtInCommandManager = new BuiltInCommandManager($banStorage, $logger);

        $builtInCommandManager->register($registeredCommand);

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

        $registeredCommand = $this->getMock(BuiltInCommand::class);

        $registeredCommand
            ->expects($this->once())
            ->method('getCommandNames')
            ->will($this->returnValue(['foo']))
        ;

        $registeredCommand
            ->expects($this->once())
            ->method('handleCommand')
            ->with($this->isInstanceOf($userCommand))
            ->willReturn(new Success())
        ;

        $logger = $this->getMock(Logger::class);

        $logger
            ->expects($this->exactly(2))
            ->method('log')
            ->withConsecutive(
                [Level::DEBUG, 'Registering command name \'foo\' with built in command ' . get_class($registeredCommand)],
                [Level::DEBUG, 'Passing event #721 to built in command handler ' . get_class($registeredCommand)]
            )
        ;

        $banStorage = $this->getMock(BanStorage::class);

        $banStorage
            ->expects($this->once())
            ->method('isBanned')
            ->willReturn(new Success(false))
        ;

        $builtInCommandManager = new BuiltInCommandManager($banStorage, $logger);

        $builtInCommandManager->register($registeredCommand);

        $this->assertNull(wait($builtInCommandManager->handleCommand($userCommand)));
    }
}
