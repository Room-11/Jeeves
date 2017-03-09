<?php declare(strict_types = 1);
 
namespace Room11\Jeeves\Tests\BuiltIn\Commands;
 
use Amp\Success;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Tests\BuiltIn\AbstractBuiltInTest;
 
abstract class AbstractCommandTest extends AbstractBuiltInTest
{
    protected $builtIn;
    protected $room;
    protected $command;
 
    public function setUp()
    {
        parent::setUp();
        $this->room = $this->createMock(Room::class);
        $this->command = $this->createMock(Command::class);
 
        $this->command
            ->method('getRoom')
            ->will($this->returnValue($this->room))
        ;
    }
 
    protected function setRoomApproval(bool $approved)
    {
        $this->room
            ->method('isApproved')
            ->will($this->returnValue(new Success($approved)))
        ;
    }
}
