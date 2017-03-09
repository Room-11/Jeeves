<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\BuiltIn;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Remove;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;

class RemoveTest extends AbstractBuiltInTest
{
    private $command; 
    private $room;
    private $admin;

    public function setUp()
    {
        parent::setUp();

        $this->admin = $this->createMock(AdminStorage::class);
        $this->command = $this->createMock(Command::class);
        $this->room = $this->createMock(Room::class);

        $this->builtIn = new Remove(
            $this->client,
            $this->admin,
            $this->getMockBuilder(PostedMessageTracker::class)->getMock()
        );
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    public function testRemoveCommandWithoutAdmin()
    {
        $this->setRoomApproval(true, 2);

        $this->admin
            ->expects($this->once())
            ->method('isAdmin')
            ->will($this->returnValue(new Success(false)))
        ;

        $this->client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($this->command),
                $this->equalTo("Sorry, you're not cool enough to do that :(")
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testUnApprovedRemoveCommand()
    {
        $this->setRoomApproval(false, 1);

        $response = \amp\wait($this->builtIn->handleCommand($this->command));
        $this->assertNull($response);
    }

    private function setRoomApproval(bool $approved, int $numberOfCalls)
    {
        $this->command
            ->expects($this->exactly($numberOfCalls))
            ->method('getRoom')
            ->will($this->returnValue($this->room))
        ;

        $this->room
            ->expects($this->once())
            ->method('isApproved')
            ->will($this->returnValue(new Success($approved)))
        ;        
    }
}
