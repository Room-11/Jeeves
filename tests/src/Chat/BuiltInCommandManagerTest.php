<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\Chat\BuiltInCommandManager;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Chat\BuiltInCommand;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Event\MessageEvent;
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

        $builtInCommandManager = new BuiltInCommandManager(
            $this->getMock(BanStorage::class),
            $logger
        );

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

        wait($builtInCommandManager->handleCommand($userCommand));
    }

    public function handleCommand(Command $command): Promise
    {
        return resolve(function() use($command) {
            $commandName = $command->getCommandName();
            if (!isset($this->commands[$commandName])) {
                return;
            }

            $eventId = $command->getEvent()->getId();

            $userId = $command->getUserId();
            $userIsBanned = yield $this->banStorage->isBanned($command->getRoom(), $userId);

            // @todo testHandleCommandWhenBanned
            if ($userIsBanned) {
                $this->logger->log(Level::DEBUG, "User #{$userId} is banned, ignoring event #{$eventId} for built in commands");
                return;
            }

            // @todo testHandleCommandMatches
            $this->logger->log(Level::DEBUG, "Passing event #{$eventId} to built in command handler " . get_class($this->commands[$commandName]));
            yield $this->commands[$commandName]->handleCommand($command);
        });
    }
}
