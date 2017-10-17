<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Ban;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\ChatClient;
use Room11\StackChat\Room\Room;

class BanTest extends AbstractCommandTest
{
    /** @var AdminStorage|\PHPUnit_Framework_MockObject_MockObject */
    private $adminStorage;

    /** @var BanStorage|\PHPUnit_Framework_MockObject_MockObject */
    private $banStorage;

    /** @var Ban|\PHPUnit_Framework_MockObject_MockObject */
    private $builtIn;

    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $client;

    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    private $command;

    /** @var Room|\PHPUnit_Framework_MockObject_MockObject */
    private $room;

    public function setUp()
    {
        parent::setUp();

        $this->adminStorage = $this->createMock(AdminStorage::class);
        $this->banStorage = $this->createMock(BanStorage::class);
        $this->client = $this->createMock(ChatClient::class);
        $this->command = $this->createMock(Command::class);
        $this->room = $this->createMock(Room::class);

        $this->builtIn = new Ban($this->client, $this->adminStorage, $this->banStorage);

        $this->setReturnValue($this->command, 'getUserId', 123);
        $this->setReturnValue($this->command, 'getRoom', $this->room);
    }

    public function testUnBanCommand()
    {
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setAdmin(new Success(true));
        $this->setReturnValue($this->command, 'getCommandName', 'unban');
        $this->setCommandParameter(0, 1234);

        $this->banStorage
            ->expects($this->once())
            ->method('remove')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(1234)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("User is unbanned.");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testBanCommand()
    {
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setAdmin(new Success(true));
        $this->setReturnValue($this->command, 'getCommandName', 'ban');
        $this->setCommandParameters([[0, 1234], [1, '10m']]);

        $this->banStorage
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(1234),
                $this->identicalTo('10m')
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("User is banned.");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testBanCommandWithoutLength()
    {
        $this->command
            ->method('hasParameters')
            ->will($this->returnValueMap([[-1, true], [2, false]]))
        ;

        $this->setAdmin(new Success(true));
        $this->setReturnValue($this->command, 'getCommandName', 'ban');
        $this->expectReply("Ban length must be specified");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testListCommand()
    {
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setAdmin(new Success(true));
        $this->setReturnValue($this->command, 'getCommandName', 'ban');
        $this->setCommandParameter(0, 'list');

        $this->setBanStorageGetAll(new Success([
                1234 => "2017-03-14 13:40:15",
                5678 => "2017-03-14 13:45:25"
            ])
        );

        $this->expectMessage(
            "1234 (2017-03-14 13:40:15), 5678 (2017-03-14 13:45:25)"
        );

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testListCommandNoBans()
    {
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setAdmin(new Success(true));
        $this->setReturnValue($this->command, 'getCommandName', 'ban');
        $this->setCommandParameter(0, 'list');
        $this->setBanStorageGetAll(new Success(false));
        $this->expectMessage("No users are currently on the naughty list.");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandWithoutAdmin()
    {
        $this->setReturnValue($this->command, 'hasParameters', true);
        $this->setAdmin(new Success(false));
        $this->expectReply("I'm sorry Dave, I'm afraid I can't do that");

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandWithNoParameters()
    {
        $this->setReturnValue($this->command, 'hasParameters', false);
        $response = \Amp\wait($this->builtIn->handleCommand($this->command));

        $this->assertNull($response);
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    private function setAdmin($value)
    {
        $this->adminStorage
            ->method('isAdmin')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(123)
            )
            ->will($this->returnValue($value))
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

    private function setCommandParameters(array $parameters)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValueMap($parameters))
        ;
    }

    private function setCommandParameter(int $parameter, $value)
    {
        $this->command
            ->method('getParameter')
            ->with($this->equalTo($parameter))
            ->will($this->returnValue($value))
        ;
    }

    private function setBanStorageGetAll($value)
    {
        $this->banStorage
            ->method('getAll')
            ->with($this->identicalTo($this->room))
            ->will($this->returnValue($value))
        ;
    }
}
