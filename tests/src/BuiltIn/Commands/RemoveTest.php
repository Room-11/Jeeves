<?php declare(strict_types = 1);
 
namespace Room11\Jeeves\Tests\BuiltIn\Commands;
 
use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Remove;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;
 
class RemoveTest extends AbstractCommandTest
{
    private $admin;
    private $tracker;
 
    public function setUp()
    {
        parent::setUp();
 
        $this->admin = $this->createMock(AdminStorage::class);
        $this->tracker = $this->createMock(PostedMessageTracker::class);
 
        $this->builtIn = new Remove(
            $this->client,
            $this->admin,
            $this->tracker
        );
    }
 
    public function testCommand()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setRoomOwner(true);
        $this->setTrackerCount(1);
 
        $this->command
            ->method('getParameter')
            ->with($this->equalTo(0))
            ->will($this->returnValue(1))
        ;        
 
        $this->command->method('getId')->will($this->returnValue(113));
 
        $message = $this->createMock(PostedMessage::class);
        $command = $this->createMock(Command::class);
 
        $this->tracker
            ->expects($this->once())
            ->method('popMessage')
            ->will($this->returnValue($message))
        ;
 
        $message->method('getId')->will($this->returnValue(112));
        $message->method('getOriginatingCommand')->will($this->returnValue($command));
        $command->method('getId')->will($this->returnValue(111));
 
        $this->client
            ->expects($this->once())
            ->method('moveMessages')
            ->with(
                $this->identicalTo($this->room),
                $this->isType('int'),
                $this->identicalTo(113),
                $this->identicalTo(112),
                $this->identicalTo(111)
            )
            ->will($this->returnValue(new Success(true)))
        ;
 
        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }
 
    public function testCommandWithoutApproval()
    {
        $this->setRoomApproval(false);
 
        $response = \amp\wait($this->builtIn->handleCommand($this->command));
        $this->assertNull($response);
    }
 
    public function testCommandWithoutAdmin()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(false);
        $this->expectReply("Sorry, you're not cool enough to do that :(");
 
        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandWithoutRoomOwner()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setRoomOwner(false);
        $this->expectReply("Sorry, I'm not a room owner so I can't do that :(");
 
        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandWithEmptyTracker()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setRoomOwner(true);
        $this->setTrackerCount(0);
 
        $this->expectReply("I don't have any messages stored for this room, sorry");
 
        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    private function setAdmin(bool $isAdmin)
    {
        $this->admin
            ->expects($this->once())
            ->method('isAdmin')
            ->will($this->returnValue(new Success($isAdmin)))
        ;        
    }    
 
    private function setRoomOwner(bool $isBotUserRoomOwner)
    {
        $this->client
            ->expects($this->once())
            ->method('isBotUserRoomOwner')
            ->will($this->returnValue(new Success($isBotUserRoomOwner)))
        ;        
    }
 
    private function setTrackerCount(int $count)
    {
        $this->tracker
            ->expects($this->once())
            ->method('getCount')
            ->will($this->returnValue($count))
        ;
    }
 
    private function expectReply(string $reply)
    {
        $this->client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($this->command),
                $this->equalTo($reply)
            )
            ->will($this->returnValue(new Success(true)))
        ;       
    }
}
